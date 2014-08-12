<?php

class bdApi_ControllerApi_Subscription extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		return $this->responseError(new XenForo_Phrase('bdapi_slash_subscription_only_accepts_post_requests'), 400);
	}

	public function actionPostIndex()
	{
		$input = $this->_input->filter(array(
			'hub_callback' => XenForo_Input::STRING,
			'hub_mode' => XenForo_Input::STRING,
			'hub_topic' => XenForo_Input::STRING,
			'hub_lease_seconds' => XenForo_Input::STRING,
		));

		if (!Zend_Uri::check($input['hub_callback']))
		{
			return $this->_responseError(new XenForo_Phrase('bdapi_subscription_callback_is_required'));
		}
		if (!XenForo_Application::getSession()->isValidRedirectUri($input['hub_callback']))
		{
			return $this->_responseError(new XenForo_Phrase('bdapi_subscription_callback_must_match'));
		}

		if (!in_array($input['hub_mode'], array(
			'subscribe',
			'unsubscribe'
		), true))
		{
			return $this->_responseError(new XenForo_Phrase('bdapi_subscription_mode_must_valid'));
		}

		if (!$this->_getSubscriptionModel()->isValidTopic($input['hub_topic']))
		{
			return $this->_responseError(new XenForo_Phrase('bdapi_subscription_topic_not_recognized'));
		}

		if ($this->_getSubscriptionModel()->verifyIntentOfSubscriber($input['hub_callback'], $input['hub_mode'], $input['hub_topic'], $input['hub_lease_seconds'], array('client_id' => $this->_getClientId())))
		{
			switch ($input['hub_mode'])
			{
				case 'unsubscribe':
					$subscriptions = $this->_getSubscriptionModel()->getSubscriptions(array(
						'client_id' => $this->_getClientId(),
						'topic' => $input['hub_topic'],
					));

					if (!empty($subscriptions))
					{
						foreach ($subscriptions as $subscription)
						{
							$dw = XenForo_DataWriter::create('bdApi_DataWriter_Subscription');
							$dw->setExistingData($subscription, true);
							$dw->delete();
						}
					}
					break;
				default:
					$dw = XenForo_DataWriter::create('bdApi_DataWriter_Subscription');
					$dw->set('client_id', $this->_getClientId());
					$dw->set('callback', $input['hub_callback']);
					$dw->set('topic', $input['hub_topic']);
					$dw->set('subscribe_date', XenForo_Application::$time);

					if ($input['hub_lease_seconds'] > 0)
					{
						$dw->set('expire_date', XenForo_Application::$time + $input['hub_lease_seconds']);
					}

					$dw->save();
			}

			return $this->_responseSuccess();
		}

		return $this->_responseError(new XenForo_Phrase('bdapi_subscription_cannot_verify_intent_of_subscriber'));
	}

	protected function _getClientId()
	{
		$session = XenForo_Application::getSession();
		return $session->getOAuthClientId();
	}

	/**
	 * @return bdApi_Model_Subscription
	 */
	protected function _getSubscriptionModel()
	{
		return $this->getModelFromCache('bdApi_Model_Subscription');
	}

	protected function _preDispatch($action)
	{
		$clientId = $this->_getClientId();
		if (empty($clientId))
		{
			throw $this->responseException($this->responseReroute('bdApi_ControllerApi_Error', 'noPermission'));
		}

		return parent::_preDispatch($action);
	}

	protected function _responseError($error)
	{
		$this->_routeMatch->setResponseType('raw');

		return $this->responseData('bdApi_ViewApi_Subscription_Post', array(
			'httpResponseCode' => 400,
			'message' => $error,
		));
	}

	protected function _responseSuccess()
	{
		$this->_routeMatch->setResponseType('raw');

		return $this->responseData('bdApi_ViewApi_Subscription_Post', array('httpResponseCode' => 202));
	}

}
