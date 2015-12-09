<?php

namespace app\rest;

use app\rest\CApi;
use yii\helpers\Json;
use Exception;

/**
 * RestApiDispatcher Dispatcher for handle request to API
 *
 * @author Валерий Православный
 */
class RestApiDispatcher
{

    /**
     * array of paramters from request
     * @var array
     */
    private $_requestData;

    /**
     * Result of request's handling
     * @var array
     */
    private $_result = [];

    /**
     * User accesss token for authorize user 
     * @var string 
     */
    private $_accessToken;

    /**
     * Parameters of JSONRPC 
     * @var array
     */
    private $_request;

    public function __construct($requestData)
    {
        $this->_requestData = $requestData;
    }

    /**
     * Process of input request
     */
    public function process()
    {
        try
        {
            //successively check input request
            if ($this->checkParameters() && $this->authorizeUser() && $this->isValidJson() && $this->checkRequestParameters())
            {
                $this->handleRequest();
            }
        } catch (Exception $e)
        {
            $this->prepareBaseErrorResult(CApi::INTERNAL_JSON_RPC_ERROR);
        }
        return $this->_result;
    }

    /**
     * Form result Array when error occured
     * @param string $errorCode
     */
    private function prepareBaseErrorResult($errorCode)
    {
        $errorsBlock = array(
            'code' => $errorCode,
            'message' => CApi::translateErrorMessage($errorCode),
        );
        $this->_result = json_encode([
            'jsonrpc' => '2.0',
            'error' => $errorsBlock,
            'id' => null,
        ]);
    }

    /**
     * Check existing of needed parameters
     * @return boolean
     */
    private function checkParameters()
    {

        if (!is_array($this->_requestData) || (!isset($this->_requestData['request']) || !isset($this->_requestData['accessToken'])))
        {
            $this->prepareBaseErrorResult(CApi::INVALID_REQUEST);
            return false;
        }
        $this->_accessToken = $this->_requestData['accessToken'];
        return true;
    }

    /**
     * Return validity status of input json
     * @return boolean
     */
    private function isValidJson()
    {
        try
        {
            $this->_request = Json::decode($this->_requestData['request']);
        } catch (\yii\base\InvalidParamException $e)
        {
            $this->prepareBaseErrorResult(CApi::PARSE_ERROR);
            return false;
        }
        return true;
    }

    /**
     * Try authorize user by Access token
     */
    public function authorizeUser()
    {
        /**
         * @todo: add authorization
         */
        return true;
    }

    /**
     * Check validity of JSON request object
     * @return boolean
     */
    private function checkRequestParameters()
    {
        if (!isset($this->_request['jsonrpc']) || !isset($this->_request['method']) || !isset($this->_request['params']) || !isset($this->_request['id']))
        {
            $this->prepareBaseErrorResult(CApi::INVALID_REQUEST);
            return false;
        }
        if (!is_array($this->_request['params']))
        {
            $this->prepareBaseErrorResult(CApi::INVALID_REQUEST);
            return false;
        }
        if (!$this->checkMethodExists())
        {
            $this->prepareBaseErrorResult(CApi::METHOD_NOT_FOUND);
            return false;
        }
        return true;
    }

    /**
     * Check existing of requested method
     * @return type
     */
    private function checkMethodExists()
    {
        return key_exists(strtolower($this->_request['method']), RestMethods::getAvaliableMethods());
    }

    /**
     * Handle input request after parameters validation;
     */
    public function handleRequest()
    {
        $handlerData = RestMethods::getMethodsHandlerData($this->_request['method']);
        $handlerClass = '\app\rest\\' . $handlerData['handler'];
        $handlerMethod = $handlerData['method'];
        $handler = new $handlerClass();
        $result = $handler->{$handlerMethod}($this->_request['params']);
        $this->prepareResponse($handler, $result);
    }

    /**
     * Form result after handle of request
     * @param \app\rest\CApi $handler
     * @param boolean $result 
     */
    private function prepareResponse(CApi $handler, $result)
    {
        if ($result)
        {
            $this->_result = json_encode([
                'jsonrpc' => '2.0',
                'result' => $handler->getResult(),
                'id' => $this->_request['id'],
            ]);
        }
        else
        {
            $errorsBlock = array(
                'code' => $handler->getErrorCode(),
                'message' => $handler->getErrorMessage(),
            );
            if ($handler->getErrorCode() == CApi::INVALID_DATA)
            {
                $errorsBlock['validationErrors'] = $handler->getValidationErrors();
                $errorsBlock['validationRelatedErrors'] = $handler->getValidationRelatedErrors();
            }
            $this->_result = json_encode([
                'jsonrpc' => '2.0',
                'error' => $errorsBlock,
                'id' => $this->_request['id'],
            ]);
        }
    }

}
