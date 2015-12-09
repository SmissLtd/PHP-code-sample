<?php

namespace app\rest;

use Yii;
use app\models\audit\AuditModification;

/**
 * Base class of Rest API functionality 
 */
abstract class CApi
{

//JSON_RPC standart errors
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMETERS = -32602;
    const INTERNAL_JSON_RPC_ERROR = -32603;
    const PARSE_ERROR = -32700;
//custom errors
    const ITEM_NOT_FOUND = -32000;
    const INVALID_DATA = -32001;
    const RELATED_ITEM_NOT_FOUND = -32002;
    const USER_NOT_AUTHENTICATED = -32003;
    const USER_NOT_AUTHORIZED = -32004;

    /**
     * Result of Rest method's working
     * @var string
     */
    protected $result = [];

    /**
     * Last error which has occurred when method was executed
     * @var integer 
     */
    protected $errorCode = false;

    /**
     * Errors which occured on model validation
     * @var array 
     */
    protected $validationErrors = [];

    /**
     * Errors which occured on validation of related entitites
     * @var array 
     */
    protected $validationRelatedErrors = [];

    /**
     * Return result of method's working as JSON object
     * @return string
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Return error code when error has occurred or false if method executed successfully
     * @return type
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Return errors which has occurred when method was executed
     * @return type
     */
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }

    /**
     * Return errors in validation of related entities which has occurred when method was executed
     * @return type
     */
    public function getValidationRelatedErrors()
    {
        return $this->validationRelatedErrors;
    }

    /**
     * Return error message when error has occurred or empty text if method executed successfully
     * @return type
     */
    public function getErrorMessage()
    {
        return self::translateErrorMessage($this->errorCode);
    }

    /**
     * return translated text messge for error code
     * @param type $errorCode
     * @return string
     */
    public static function translateErrorMessage($errorCode)
    {
        if ($errorCode === false)
            return '';

        switch ($errorCode)
        {
            case self::ITEM_NOT_FOUND:
                return Yii::t('app', 'Item not found.');
            case self::INVALID_DATA:
                return Yii::t('app', 'Invalid data. See validation errors.');
            case self::INVALID_PARAMETERS:
                return Yii::t('app', 'Invalid method parameter(s).');
            case self::RELATED_ITEM_NOT_FOUND:
                return Yii::t('app', 'Related item for create relation was not found');
            case self::INVALID_REQUEST:
                return Yii::t('app', 'The JSON sent is not a valid Request object.');
            case self::METHOD_NOT_FOUND:
                return Yii::t('app', 'The method does not exist / is not available.');
            case self::INTERNAL_JSON_RPC_ERROR:
                return Yii::t('app', 'Internal JSON-RPC error.');
            case self::PARSE_ERROR:
                return Yii::t('app', 'Parse error');
            case self::USER_NOT_AUTHENTICATED:
                return Yii::t('app', 'User was not authenticated');
            case self::USER_NOT_AUTHORIZED:
                return Yii::t('app', 'User was not authorized');
            default:
                return Yii::t('app', 'Undefined');
        }
    }

    /**
     * return base model for
     */
    abstract protected function getModelName();

    /**
     * check user permissions to access for this record
     * @param yii\db\ActiveRecord $model
     * @return boolean
     */
    protected function checkPermission($model)
    {
        return true;
    }
    
    /**
     * Add additional data to item info result     
     * @param yii\db\ActiveRecord $model
     */
    protected function addAdditionalData($model)
    {        
    }

    /**
     * Get Record by requested id
     * @param array $params
     * @return boolean
     */
    public function getItem(Array $params)
    {
        $modelName = $this->getModelName();
        if (!isset($params['id']))
        {
            $this->errorCode = CApi::INVALID_PARAMETERS;
            return false;
        }
        $model = $modelName::findOne($params['id']);
        if (!$this->checkPermission($model))
        {
            $this->errorCode = CApi::USER_NOT_AUTHORIZED;
            return false;
        }
        if (empty($model))
        {
            $this->errorCode = CApi::ITEM_NOT_FOUND;
            return false;
        }
        $this->result = $model->getData();        
        $this->addAdditionalData($model);
        return true;
    }

    /**
     * Update or Create record  in table
     * @param array $params
     * @return boolean
     */
    public function saveItem(Array $params)
    {
        $modelName = $this->getModelName();
        if (!isset($params['id']))
        {
            $model = new $modelName();
            $action = 'create';
        }
        else
        {
            $model = $modelName::findOne($params['id']);
            $action = 'update';
        }
        if (!$this->checkPermission($model))
        {
            $this->errorCode = CApi::USER_NOT_AUTHORIZED;
            return false;
        }

        foreach ($params as $key => $value)
        {
            if (($key !== 'id') && (in_array($key, $model->getRestAttributes())))
            {
                $model->$key = $value;
            }
        }

        if ($model->validate())
        {
            $model->save(false, null, $this->getModification($action));
            $this->result = [
                'id' => $model->id,
                'action' => $action,
            ];
            return true;
        }
        else
        {
            $this->errorCode = CApi::INVALID_DATA;
            $this->validationErrors = $model->errors;
            return false;
        }
    }

    /**
     * Prepare result for get all records from table
     * @param array $params
     * @return boolean
     */
    public function getList(Array $params)
    {
        $query = $this->getBaseListQuery($params);
        $this->prepareListData($query);
        return true;
    }

    /**
     * Execute prepared ActiveQuery and create List of items for answer
     * @param \yii\db\ActiveQuery $query
     */
    protected function prepareListData(\yii\db\ActiveQuery $query)
    {
        $data = $query->all();
        $result = [
            'totalCount' => $query->count(),
            'list' => []
        ];
        foreach ($data as $record)
            $result['list'][] = $record->getData();
        $this->result = $result;
    }

    /**
     * Create base query and add limit/offset parameters for get list
     * @param array $params
     * @return \yii\db\ActiveQuery
     */
    protected function getBaseListQuery(Array $params)
    {
        $modelName = $this->getModelName();
        $query = $modelName::find();
        if (isset($params['limit']) && is_numeric($params['limit']))
            $query->limit($params['limit']);
        if (isset($params['offset']) && is_numeric($params['offset']))
            $query->offset($params['offset']);
        return $query;
    }

    protected function getModification($action)
    {
        $modification = new AuditModification();
        $modification->user_id = (Yii::$app->user->id) ? (Yii::$app->user->id) : 0;
        $modification->action = $action;
        $modification->comment = '';        
        $modification->save(false);
        return $modification;
    }

}
