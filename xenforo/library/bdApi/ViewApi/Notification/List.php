<?php

class bdApi_ViewApi_Notification_List extends bdApi_ViewApi_Base
{
	public function prepareParams()
	{
		// render notification html
		$notifications = &$this->_params['notifications'];
		$templates = XenForo_ViewPublic_Helper_Alert::getTemplates($this, $this->_params['_alerts'], $this->_params['_alertHandlers']);
		foreach ($notifications as $key => &$notification)
		{
			$notification['notification_html'] = strval($templates[$notification['notification_id']]['template']);
		}

		// subscription discovery
		$hubLink = XenForo_Link::buildApiLink('subscriptions', null, array(
			'hub.topic' => bdApi_Model_Subscription::getTopic(bdApi_Model_Subscription::TYPE_NOTIFICATION, XenForo_Visitor::getUserId()),
			OAUTH2_TOKEN_PARAM_NAME => '',
		));
		$this->_response->setHeader('Link', sprintf('<%s>; rel=hub', $hubLink));
		$selfLink = XenForo_Link::buildApiLink('notifications', null, array(OAUTH2_TOKEN_PARAM_NAME => ''));
		$this->_response->setHeader('Link', sprintf('<%s>; rel=self', $selfLink));

		// subscription info
		$visitor = XenForo_Visitor::getInstance()->toArray();
		if (!empty($visitor['bdapi_user_notification']))
		{
			$userOption = @unserialize($visitor['bdapi_user_notification']);
			if (!empty($userOption['subscriptions']))
			{
				$clientId = XenForo_Application::getSession()->getOAuthClientId();
				foreach ($userOption['subscriptions'] as $subscription)
				{
					if ($subscription['client_id'] == $clientId)
					{
						$this->_params['subscription_callback'] = $subscription['callback'];
					}
				}
			}
		}

		return parent::prepareParams();
	}

}
