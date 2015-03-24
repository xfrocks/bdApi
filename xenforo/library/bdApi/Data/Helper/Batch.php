<?php

class bdApi_Data_Helper_Batch
{
    public static function getFc()
    {
        static $fcTemp = null;

        if ($fcTemp === null) {
            if (!XenForo_Application::isRegistered('_bdApi_fc')) {
                throw new XenForo_Exception('API front controller cannot be found.');
            }

            /* @var $fc XenForo_FrontController */
            $fc = XenForo_Application::get('_bdApi_fc');

            $fcTemp = new XenForo_FrontController($fc->getDependencies());
            $fcTemp->setup();
        }

        return $fcTemp;
    }

    public static function doJob($method, $uri, array $params)
    {
        $fc = self::getFc();

        $request = new bdApi_Zend_Controller_Request_Http(XenForo_Link::convertApiUriToAbsoluteUri($uri, true));
        $request->setMethod($method);
        foreach ($params as $key => $value) {
            $request->setParam($key, $value);
        }
        $fc->setRequest($request);

        // routing
        $routeMatch = $fc->getDependencies()->route($request);
        if (!$routeMatch OR !$routeMatch->getControllerName()) {
            list($controllerName, $action) = $fc->getDependencies()->getNotFoundErrorRoute();
            $routeMatch->setControllerName($controllerName);
            $routeMatch->setAction($action);
        }

        $response = $fc->dispatch($routeMatch);

        if ($response instanceof XenForo_ControllerResponse_Error) {
            return array(
                '_job_result' => 'error',
                '_job_error' => $response->errorText,
            );
        } elseif ($response instanceof XenForo_ControllerResponse_Exception) {
            return array(
                '_job_result' => 'error',
                '_job_error' => $response->getMessage(),
            );
        } elseif ($response instanceof XenForo_ControllerResponse_Message) {
            return array(
                '_job_result' => 'message',
                '_job_message' => $response->message,
            );
        } elseif ($response instanceof XenForo_ControllerResponse_View) {
            return array_merge(array('_job_result' => 'ok'), $response->params);
        }

        throw new XenForo_Exception('Unexpected $response occurred.');
    }
}