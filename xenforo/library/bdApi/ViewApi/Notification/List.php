<?php

class bdApi_ViewApi_Notification_List extends bdApi_ViewApi_Base
{
	public function renderJson()
	{
		$notifications = $this->_params['notifications'];

		$templates = XenForo_ViewPublic_Helper_Alert::getTemplates($this, $this->_params['alerts'], $this->_params['alertHandlers']);

		foreach ($notifications as $key => &$notification)
		{
			$notification['notification_html'] = strval($templates[$notification['notification_id']]['template']);
		}

		return array($notifications);
	}

}
