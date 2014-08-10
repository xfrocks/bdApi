<?php

class bdApi_ControllerApi_Notification extends bdApi_ControllerApi_Abstract
{

	protected function _preDispatch($action)
	{
		$this->_assertRegistrationRequired();

		return parent::_preDispatch($action);
	}

	public function actionGetIndex()
	{
		$alertModel = $this->_getAlertModel();
		$visitor = XenForo_Visitor::getInstance();

		$alertResults = $alertModel->getAlertsForUser($visitor['user_id'], XenForo_Model_Alert::FETCH_MODE_POPUP);

		$alerts = array();
		foreach ($alertResults['alerts'] AS $alertId => $alert)
		{
			if ($alert['unviewed'])
			{
				$alerts[$alertId] = $alert;
			}
		}

		$data = array(
			'notifications' => $this->_filterDataMany($this->_getAlertModel()->prepareApiDataForAlerts($alerts)),

			'_alerts' => $alerts,
			'_alertHandlers' => $alertResults['alertHandlers'],
		);

		return $this->responseData('bdApi_ViewApi_Notification_List', $data);
	}

	/**
	 *
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}

}
