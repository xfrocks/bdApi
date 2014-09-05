<?php

class bdApi_ControllerApi_Batch extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		return $this->responseError(new XenForo_Phrase('bdapi_slash_batch_only_accepts_post_requests'), 400);
	}

	public function actionPostIndex()
	{
		/* @var $fc XenForo_FrontController */
		$fc = XenForo_Application::get('_bdApi_fc');

		$fcTemp = new XenForo_FrontController($fc->getDependencies());
		$fcTemp->setup();

		$input = file_get_contents('php://input');
		$batchJobs = @json_decode($input, true);
		if (empty($batchJobs))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_batch_requires_json'), 400);
		}
		$jobsOutput = array();

		foreach ($batchJobs as $batchJob)
		{
			if (empty($batchJob['uri']))
			{
				continue;
			}

			if (empty($batchJob['id']))
			{
				$number = 0;

				do
				{
					if ($number == 0)
					{
						$id = $batchJob['uri'];
					}
					else
					{
						$id = sprintf('%s_%d', $batchJob['uri'], $number);
					}

					$number++;
				}
				while (isset($jobsOutput[$id]));
			}
			else
			{
				$id = $batchJob['id'];
			}

			if (empty($batchJob['method']))
			{
				$method = 'GET';
			}
			else
			{
				$method = strtoupper($batchJob['method']);
			}

			if (empty($batchJob['params']) OR !is_array($batchJob['params']))
			{
				$params = array();
			}
			else
			{
				$params = $batchJob['params'];
			}

			$params = array_merge($this->_extractUriParams($batchJob['uri']), $params);

			$jobsOutput[$id] = $this->_doJob($fcTemp, $method, $batchJob['uri'], $params);
		}

		$data = array('jobs' => $jobsOutput, );

		return $this->responseData('bdApi_ViewApi_Batch_Index', $data);
	}

	protected function _extractUriParams(&$uri)
	{
		$params = array();

		$parsed = parse_url($uri);
		if (!empty($parsed['query']))
		{
			parse_str($parsed['query'], $params);
		}

		return $params;
	}

	protected function _doJob(XenForo_FrontController $fc, $method, $uri, array $params)
	{
		$request = new bdApi_Zend_Controller_Request_Http(XenForo_Link::convertApiUriToAbsoluteUri($uri, true));
		$request->setMethod($method);
		foreach ($params as $key => $value)
		{
			$request->setParam($key, $value);
		}
		$fc->setRequest($request);

		// routing
		$routeMatch = $fc->getDependencies()->route($request);
		if (!$routeMatch OR !$routeMatch->getControllerName())
		{
			list($controllerName, $action) = $fc->getDependencies()->getNotFoundErrorRoute();
			$routeMatch->setControllerName($controllerName);
			$routeMatch->setAction($action);
		}

		$response = $fc->dispatch($routeMatch);

		if ($response instanceof XenForo_ControllerResponse_Error)
		{
			return array(
				'_job_result' => 'error',
				'_job_error' => $response->errorText,
			);
		}
		elseif ($response instanceof XenForo_ControllerResponse_Exception)
		{
			return array(
				'_job_result' => 'error',
				'_job_error' => $response->getMessage(),
			);
		}
		elseif ($response instanceof XenForo_ControllerResponse_Message)
		{
			return array(
				'_job_result' => 'message',
				'_job_message' => $response->message,
			);
		}
		elseif ($response instanceof XenForo_ControllerResponse_Redirect)
		{
			// this should not happen
			throw new XenForo_Exception('Unexpected `XenForo_ControllerResponse_Redirect` occured.');
		}
		elseif ($response instanceof XenForo_ControllerResponse_Reroute)
		{
			// this should not happen
			throw new XenForo_Exception('Unexpected `XenForo_ControllerResponse_Reroute` occured.');
		}
		elseif ($response instanceof XenForo_ControllerResponse_View)
		{
			return array_merge(array('_job_result' => 'ok'), $response->params);
		}
	}

	protected function _getScopeForAction($action)
	{
		// scope check will be perform by each individual controller later
		return false;
	}

	protected function _logRequest($controllerResponse, $controllerName, $action)
	{
		// skip logging for successful /batch request
		if ($controllerResponse instanceof XenForo_ControllerResponse_View)
		{
			return false;
		}

		return parent::_logRequest($controllerResponse, $controllerName, $action);
	}

}
