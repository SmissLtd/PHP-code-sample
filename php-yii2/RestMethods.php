<?php

namespace app\rest;

class RestMethods
{

    private static $_map = [
        'getlist'  => ['handler' => 'TestApi', 'method' => 'getList'],
        'getitem'  => ['handler' => 'TestApi', 'method' => 'getItem'],
        'saveitem' => ['handler' => 'TestApi', 'method' => 'saveItem'],
    ];

    public static function getAvaliableMethods()
    {
        return self::$_map;
    }
    
    /**     
     * Return array with info : Handler Class name and method which need for call method
     * @param string $method
     * @return array
     */
    public static function getMethodsHandlerData($method)
    {        
        return self::$_map[strtolower($method)];
    }

}
