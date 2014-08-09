<?php

class bdApi_XenForo_Model_Alert extends XFCP_bdApi_XenForo_Model_Alert
{
	public function prepareApiDataForAlerts(array $alerts)
	{
		$data = array();

		foreach ($alerts as $key => $alert)
		{
			$data[] = $this->prepareApiDataForAlert($alert);
		}

		return $data;
	}

	public function prepareApiDataForAlert(array $alert)
	{
		$publicKeys = array(
			// xf_user_alert
			'alert_id' => 'notification_id',
			'event_date' => 'notification_create_date',
		);

		$data = bdApi_Data_Helper_Core::filter($alert, $publicKeys);

		if (!empty($alert['user']))
		{
			$data['creator_user_id'] = $alert['user']['user_id'];
			$data['creator_username'] = $alert['user']['username'];
		}

		if (!empty($alert['content_type']) AND !empty($alert['content_id']) AND !empty($alert['action']))
		{
			$data['notification_type'] = sprintf('%s_%d_%s', $alert['content_type'], $alert['content_id'], $alert['action']);
		}

		return $data;
	}

}
