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
        if (XenForo_Application::isRegistered('_bdApi_disableBatch')
            && XenForo_Application::get('_bdApi_disableBatch')
        ) {
            return array(
                '_job_result' => 'error',
                '_job_error' => 'Batch running has been disabled.',
            );
        }

        $fc = self::getFc();

        $request = new bdApi_Zend_Controller_Request_Http(bdApi_Data_Helper_Core::safeConvertApiUriToAbsoluteUri($uri, true));
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
            return array(
                '_job_result' => 'ok',
                '_job_response' => $response,
            );
        }

        throw new XenForo_Exception('Unexpected $response occurred.');
    }

    public static function prepareViewParams(XenForo_ViewRenderer_Abstract $viewRenderer, XenForo_View $view)
    {
        $params = $view->getParams();
        $jobs = array();

        if (!empty($params['jobs'])) {
            foreach ($params['jobs'] as $jobId => $job) {
                $preparedJob = array();

                if (empty($job['_job_result'])) {
                    continue;
                }
                $preparedJob['_job_result'] = $job['_job_result'];

                switch ($job['_job_result']) {
                    case 'error':
                        if (empty($job['_job_error'])) {
                            continue;
                        }

                        $preparedJob['_job_error'] = $job['_job_error'];
                        break;
                    case 'message':
                        if (empty($job['_job_message'])) {
                            continue;
                        }

                        $preparedJob['_job_message'] = $job['_job_message'];
                        break;
                    case 'ok':
                        if (empty($job['_job_response'])) {
                            continue;
                        }
                        /** @var XenForo_ControllerResponse_View $response */
                        $response = $job['_job_response'];

                        $viewOutput = $viewRenderer->renderViewObject($response->viewName, 'Json', $response->params, $response->templateName);
                        if (!is_array($viewOutput)) {
                            $viewOutput = @json_decode($viewOutput, true);
                        }

                        if (is_array($viewOutput)) {
                            $preparedJob = array_merge($preparedJob, $viewOutput);
                        } elseif ($viewOutput === null) {
                            $preparedJob = array_merge($preparedJob, $response->params);
                        }
                        break;
                    default:
                        continue;
                }

                $jobs[$jobId] = $preparedJob;
            }
        }

        return array(
            'jobs' => $jobs,
        );
    }
}