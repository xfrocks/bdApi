<?php

class bdApiConsumer_ControllerPublic_Callback extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$method = $this->_request->getMethod();
		if (empty($method))
		{
			throw new XenForo_Exception('Unable to determine request method');
		}
		if (strtoupper($method) === 'GET')
		{
			return $this->responseReroute(__CLASS__, 'IntentVerification');
		}
		elseif (strtoupper($method) !== 'POST')
		{
			throw new XenForo_Exception('Unrecognized request method: ' . $method);
		}

		return $this->responseReroute(__CLASS__, 'PingPong');
	}

	public function actionIntentVerification()
	{
		$params = $this->_input->filter(array(
			'client_id' => XenForo_Input::STRING,
			'hub_topic' => XenForo_Input::STRING,
			'hub_challenge' => XenForo_Input::STRING,
		));

		if (empty($params['client_id']))
		{
			// unable to determine hub authorized client
			header('HTTP/1.0 404 Not Found');
			exit ;
		}
		$providers = bdApiConsumer_Option::get('providers');
		$foundProvider = null;
		foreach ($providers as $provider)
		{
			if (!empty($provider['client_id']) AND $provider['client_id'] == $params['client_id'])
			{
				$foundProvider = $provider;
				break;
			}
		}
		if (empty($foundProvider))
		{
			// client not found
			header('HTTP/1.0 401 Unauthorized');
			exit ;
		}

		// TODO: verify $params['hub_topic']?

		echo $params['hub_challenge'];
		exit ;
	}

	public function actionPingPong()
	{
		$results = array();
		$raw = file_get_contents('php://input');
		$json = @json_decode($raw, true);
		if (!is_array($json))
		{
			throw new XenForo_Exception('Unable to parse JSON: ' . $raw);
		}

		$providers = $providers = bdApiConsumer_Option::get('providers');
		$providerPings = array();

		foreach ($json as $ping)
		{
			if (empty($ping['client_id']))
			{
				continue;
			}
			$foundProviderKey = null;
			foreach ($providers as $providerKey => $provider)
			{
				if (!empty($provider['client_id']) AND $provider['client_id'] == $ping['client_id'])
				{
					$foundProviderKey = $providerKey;
					break;
				}
			}
			if (empty($foundProviderKey))
			{
				continue;
			}

			if (empty($ping['topic']))
			{
				continue;
			}
			$parts = explode('_', $ping['topic']);
			$ping['topic_id'] = array_pop($parts);
			$ping['topic_type'] = implode('_', $parts);

			$providerPings[$providerKey][$ping['topic_type']][$ping['topic_id']] = $ping;
		}

		foreach ($providerPings as $providerKey => &$manyTopics)
		{
			foreach ($manyTopics as $topicType => &$topicPings)
			{
				$result = null;

				switch ($topicType)
				{
					case 'user':
						$this->_handleUserPings($providers[$providerKey], $topicPings);
				}

				foreach ($topicPings as $ping)
				{
					if (!empty($ping['result']))
					{
						$results[] = $ping;
					}
				}
			}
		}

		echo json_encode($results);
		exit ;
	}

	protected function _handleUserPings(array $provider, array &$pings)
	{
		$userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');

		$providerKeys = array();
		foreach ($pings as &$ping)
		{
			$providerKeys[] = $ping['object_data'];
		}

		$auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociationsForProviderUser($provider, $providerKeys);

		foreach ($auths as $auth)
		{
			$accessToken = $userExternalModel->bdApiConsumer_getAccessTokenFromAuth($provider, $auth);
			if (empty($accessToken))
			{
				continue;
			}

			$externalVisitor = bdApiConsumer_Helper_Api::getVisitor($provider, $accessToken, false);
			if (empty($externalVisitor))
			{
				continue;
			}

			$userExternalModel->bdApiConsumer_updateExternalAuthAssociation($provider, $auth['provider_key'], $auth['user_id'], array_merge($auth['extra_data'], $externalVisitor));

			foreach ($pings as &$ping)
			{
				if ($ping['object_data'] == $auth['provider_key'])
				{
					$ping['result'] = 'updated user data';
				}
			}
		}
	}

	protected function _checkCsrf($action)
	{
		// no csrf check for this
		return;
	}

}
