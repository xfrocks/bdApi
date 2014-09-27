<?php

class bdApiConsumer_XenForo_Model_Alert extends XFCP_bdApiConsumer_XenForo_Model_Alert
{
	protected $_bdApiConsumer_unreadAlertProviders = array();

	public function resetUnreadAlertsCounter($userId)
	{
		if ($userId == XenForo_Visitor::getUserId() AND !empty($this->_bdApiConsumer_unreadAlertProviders[$userId]))
		{
			$viewingUser = null;
			$this->standardizeViewingUserReference($viewingUser);

			$this->_bdApiConsumer_markExternalAlertsRead($viewingUser, array_keys($this->_bdApiConsumer_unreadAlertProviders[$userId]));
		}

		return parent::resetUnreadAlertsCounter($userId);
	}

	public function bdApiConsumer_alertUser($provider, $user, $notification)
	{
		return call_user_func_array(array(
			$this,
			'alertUser'
		), array(
			$user['user_id'],
			0,
			$provider['name'],
			'bdapi_consumer',
			0,
			$provider['code'],
			array('notification' => $notification),
		));
	}

	public function bdApiConsumer_markAlertsRead($provider, $user, $time = null)
	{
		if ($time === null)
		{
			$time = XenForo_Application::$time;
		}

		$updated = $this->_getDb()->update('xf_user_alert', array('view_date' => $time), array(
			'alerted_user_id = ?' => $user['user_id'],
			'view_date = ?' => 0,
			'content_type = ?' => 'bdapi_consumer',
			'action = ?' => $provider['code'],
		));

		if ($updated > 0)
		{
			$this->_getDb()->query('
				UPDATE `xf_user`
				SET alerts_unread = IF(alerts_unread > ?, alerts_unread - ?, 0)
				WHERE user_id = ?
			', array(
				$updated,
				$updated,
				$user['user_id']
			));
		}

		return $updated;
	}

	public function getAlertsForUser($userId, $fetchMode, array $fetchOptions = array(), array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);
		$shouldWork = (bdApiConsumer_Option::get('displayExternalNotifications') AND $userId == $viewingUser['user_id']);

		if ($shouldWork)
		{
			// only check external server when in recent mode
			// for popup mode, only check if subscription is not confirmed
			$alwaysCheck = false;
			if ($fetchMode == XenForo_Model_Alert::FETCH_MODE_RECENT)
			{
				if (empty($fetchOptions['page']) OR $fetchOptions['page'] == 1)
				{
					$alwaysCheck = true;
				}
			}

			$this->_bdApiConsumer_getExternalAlertsForUser($viewingUser, $alwaysCheck);
		}

		$alerts = parent::getAlertsForUser($userId, $fetchMode, $fetchOptions, $viewingUser);

		if ($shouldWork AND !empty($alerts['alerts']))
		{
			foreach ($alerts['alerts'] as $alert)
			{
				if (empty($alert['view_date']) AND !empty($alert['content_type']) AND !empty($alert['action']))
				{
					if ($alert['content_type'] == 'bdapi_consumer')
					{
						$this->_bdApiConsumer_unreadAlertProviders[$userId][$alert['action']] = true;
					}
				}
			}
		}

		return $alerts;
	}

	protected function _bdApiConsumer_getExternalAlertsForUser(array $viewingUser, $alwaysCheck)
	{
		$userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
		$auths = $this->getModelFromCache('XenForo_Model_UserExternal')->bdApiConsumer_getExternalAuthAssociations($viewingUser['user_id']);

		foreach ($auths as &$authRef)
		{
			if (!XenForo_Model_Alert::userReceivesAlert($viewingUser, 'bdapi_consumer', $authRef['provider']))
			{
				// user opted out for this provider
				continue;
			}

			if (!$alwaysCheck AND empty($auth['extra_data']['notification_subscription_callback']))
			{
				// do not check to save resources
				continue;
			}

			$provider = bdApiConsumer_Option::getProviderByCode($authRef['provider']);
			if (empty($provider))
			{
				continue;
			}
			$accessToken = $userExternalModel->bdApiConsumer_getAccessTokenFromAuth($provider, $authRef);
			if (empty($accessToken))
			{
				continue;
			}

			$notifications = bdApiConsumer_Helper_Api::getNotifications($provider, $accessToken);
			$alertUser = true;

			if (!empty($notifications['_headerLinkHub']))
			{
				if (empty($notifications['subscription_callback']))
				{
					// subscribe to future notifications
					if (bdApiConsumer_Helper_Api::postSubscription($provider, $accessToken, $notifications['_headerLinkHub']))
					{
						$authRef['extra_data']['notification_subscription_callback'] = 1;
						$userExternalModel->bdApiConsumer_updateExternalAuthAssociation($provider, $authRef['provider_key'], $authRef['user_id'], $authRef['extra_data']);
					}
				}
				else
				{
					// subscribed, do not alert user to avoid duplicates
					$alertUser = false;
				}
			}

			if ($alertUser AND !empty($notifications['notifications']))
			{
				// server does not support subscription, we will just put all notifications into
				// user alerts then
				foreach ($notifications['notifications'] as $notification)
				{
					$this->bdApiConsumer_alertUser($provider, $viewingUser, $notification);
				}
			}
		}
	}

	protected function _bdApiConsumer_markExternalAlertsRead(array $viewingUser, array $providerCodes)
	{
		$userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
		$auths = $this->getModelFromCache('XenForo_Model_UserExternal')->bdApiConsumer_getExternalAuthAssociations($viewingUser['user_id']);

		foreach ($auths as &$authRef)
		{
			$provider = bdApiConsumer_Option::getProviderByCode($authRef['provider']);
			if (empty($provider))
			{
				continue;
			}

			if (!in_array($provider['code'], $providerCodes, true))
			{
				continue;
			}

			$accessToken = $userExternalModel->bdApiConsumer_getAccessTokenFromAuth($provider, $authRef);
			if (empty($accessToken))
			{
				continue;
			}

			bdApiConsumer_Helper_Api::postNotificationsRead($provider, $accessToken);
		}
	}

}
