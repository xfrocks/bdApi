<?php

class bdApi_Data_Helper_Cors
{
    public static function addHeaders(Zend_Controller_Response_Abstract $response)
    {
        $response->setHeader('Access-Control-Allow-Origin', '*');
    }
}