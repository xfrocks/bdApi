<?php

class bdApi_ViewApi_Notification_List extends bdApi_ViewApi_Base
{
	public function prepareParams()
	{
		// subscription discovery
		$hubLink = bdApi_Link::buildApiLink('subscriptions', null, array(
			'hub.topic' => bdApi_Model_Subscription::getTopic(bdApi_Model_Subscription::TYPE_NOTIFICATION, XenForo_Visitor::getUserId()),
			OAUTH2_TOKEN_PARAM_NAME => '',
		));
		$this->_response->setHeader('Link', sprintf('<%s>; rel=hub', $hubLink));
		$selfLink = bdApi_Link::buildApiLink('notifications', null, array(OAUTH2_TOKEN_PARAM_NAME => ''));
		$this->_response->setHeader('Link', sprintf('<%s>; rel=self', $selfLink));

		return parent::prepareParams();
	}

	public function renderJson()
	{
		$notifications = $this->_params['notifications'];

		$templates = XenForo_ViewPublic_Helper_Alert::getTemplates($this, $this->_params['alerts'], $this->_params['alertHandlers']);

		foreach ($notifications as $key => &$notification)
		{
			$notification['notification_html'] = strval($templates[$notification['notification_id']]['template']);
		}

		return array('notifications' => $notifications);
	}

}
