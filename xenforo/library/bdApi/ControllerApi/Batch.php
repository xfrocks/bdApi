<?php

class bdApi_ControllerApi_Batch extends bdApi_ControllerApi_Abstract
{
    public function actionPostIndex()
    {
        $input = file_get_contents('php://input');
        $batchJobs = @json_decode($input, true);
        if (empty($batchJobs)) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_batch_requires_json'), 400);
        }
        $jobsOutput = array();

        foreach ($batchJobs as $batchJob) {
            if (empty($batchJob['uri'])) {
                continue;
            }

            if (empty($batchJob['id'])) {
                $number = 0;

                do {
                    if ($number == 0) {
                        $id = $batchJob['uri'];
                    } else {
                        $id = sprintf('%s_%d', $batchJob['uri'], $number);
                    }

                    $number++;
                } while (isset($jobsOutput[$id]));
            } else {
                $id = $batchJob['id'];
            }

            if (empty($batchJob['method'])) {
                $method = 'GET';
            } else {
                $method = strtoupper($batchJob['method']);
            }

            if (empty($batchJob['params']) OR !is_array($batchJob['params'])) {
                $params = array();
            } else {
                $params = $batchJob['params'];
            }

            $params = array_merge($this->_extractUriParams($batchJob['uri']), $params);

            $jobsOutput[$id] = bdApi_Data_Helper_Batch::doJob($method, $batchJob['uri'], $params);
        }

        $data = array('jobs' => $jobsOutput);

        return $this->responseData('bdApi_ViewApi_Batch_Index', $data);
    }

    protected function _extractUriParams(&$uri)
    {
        $params = array();

        $parsed = parse_url($uri);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $params);
        }

        return $params;
    }

    protected function _getScopeForAction($action)
    {
        // scope check will be perform by each individual controller later
        return false;
    }

    protected function _logRequest($controllerResponse, $controllerName, $action)
    {
        // skip logging for successful /batch request
        if ($controllerResponse instanceof XenForo_ControllerResponse_View) {
            return false;
        }

        return parent::_logRequest($controllerResponse, $controllerName, $action);
    }
}
