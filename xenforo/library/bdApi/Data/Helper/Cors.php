<?php

class bdApi_Data_Helper_Cors
{
    public static function addHeaders(XenForo_ViewRenderer_Abstract $viewRenderer, Zend_Controller_Response_Http $response)
    {
        if (!bdApi_Option::get('cors')) {
            return;
        }

        $request = $viewRenderer->getRequest();

        $origin = $request->getHeader('Origin');
        if (!empty($origin)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin, true);
            $response->setHeader('Access-Control-Allow-Credentials', 'true', true);
        } else {
            $response->setHeader('Access-Control-Allow-Origin', '*', true);
        }

        $method = $request->getHeader('Access-Control-Request-Method');
        if (!empty($method)) {
            $response->setHeader('Access-Control-Allow-Method', $method, true);
        }

        $headers = $request->getHeader('Access-Control-Request-Headers');
        if (!empty($headers)) {
            $response->setHeader('Access-Control-Allow-Headers', $headers, true);
        }
    }
}