<?php

class bdApi_XenForo_Model_Alert extends XFCP_bdApi_XenForo_Model_Alert
{
    public function resetUnreadAlertsCounter($userId)
    {
        if (bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_NOTIFICATION)) {
            // subscription for alert is enabled
            $userOption = $this->bdApi_getUserNotificationOption($userId);
            if (!empty($userOption)) {
                /* @var $subscriptionModel bdApi_Model_Subscription */
                $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
                $subscriptionModel->ping(
                    $userOption,
                    'read',
                    bdApi_Model_Subscription::TYPE_NOTIFICATION,
                    bdApi_AlertHandler_Ping::fakeAlert(
                        $userId,
                        array('read_notification_id' => 'all')
                    )
                );
            }
        }

        parent::resetUnreadAlertsCounter($userId);
    }

    public function bdApi_getAlertsByIds($alertIds)
    {
        return $this->fetchAllKeyed('
				SELECT
					alert.*,
					user.gender, user.avatar_date, user.gravatar, user.username
				FROM xf_user_alert AS alert
				INNER JOIN xf_user AS user ON (user.user_id = alert.user_id)
				WHERE alert.view_date = 0
					AND alert.alert_id IN (' . $this->_getDb()->quote($alertIds) . ')
				ORDER BY event_date DESC
		', 'alert_id');
    }

    public function bdApi_prepareContentForAlerts($alerts, $viewingUser)
    {
        $alerts = $this->_getContentForAlerts($alerts, $viewingUser['user_id'], $viewingUser);
        $alerts = $this->_getViewableAlerts($alerts, $viewingUser);

        $alerts = $this->prepareAlerts($alerts, $viewingUser);

        return $alerts;
    }

    public function bdApi_getAlertHandlers()
    {
        return $this->_handlerCache;
    }

    public function bdApi_getUserNotificationOption($userId)
    {
        if (XenForo_Application::isRegistered('bdapi_user_notification')) {
            $userOptions = XenForo_Application::get('bdapi_user_notification');
        } else {
            $userOptions = array();
        }

        if (empty($userOptions) OR !isset($userOptions[$userId])) {
            $subColumn = bdApi_Option::getConfig('subscriptionColumnUserNotification');
            if ($userId == XenForo_Visitor::getUserId()) {
                $userOptions[$userId] = XenForo_Visitor::getInstance()->get($subColumn);
            } else {
                $userOptions[$userId] = $this->_getDb()->fetchOne('
					SELECT `' . $subColumn . '`
					FROM `xf_user_option`
					WHERE user_id = ?
				', $userId);
            }

            XenForo_Application::set('bdapi_user_notification', $userOptions);
        }

        $userOption = $userOptions[$userId];

        if (!empty($userOption)) {
            $userOption = @unserialize($userOption);
        }

        if (empty($userOption)) {
            $userOption = array();
        }

        return $userOption;
    }

    public function getAlertOptOuts(array $user = null, $useDenormalized = true)
    {
        $subColumn = bdApi_Option::getConfig('subscriptionColumnUserNotification');
        if (!empty($user['user_id']) AND isset($user[$subColumn])) {
            if (XenForo_Application::isRegistered('bdapi_user_notification')) {
                $userOptions = XenForo_Application::get('bdapi_user_notification');
            } else {
                $userOptions = array();
            }

            $userOptions[$user['user_id']] = $user[$subColumn];

            XenForo_Application::set('bdapi_user_notification', $userOptions);
        }

        return parent::getAlertOptOuts($user, $useDenormalized);
    }

    public function prepareApiDataForAlerts(array $alerts)
    {
        $data = array();

        foreach ($alerts as $key => $alert) {
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
            'user_id' => 'creator_user_id',
            'username' => 'creator_username',

            'content_type' => 'content_type',
            'content_id' => 'content_id',
            'action' => 'content_action',

            // XenForo_Model_Alert::prepareAlert
            'unviewed' => 'notification_is_unread',
        );

        $data = bdApi_Data_Helper_Core::filter($alert, $publicKeys);

        if ($alert['content_type'] === 'bdalerts_groupped' && is_array($alert['alerts'])) {
            $firstAlert = reset($alert['alerts']);
            $data['content_type'] = $firstAlert['content_type'];
            $data['content_id'] = $firstAlert['content_id'];
            $data['content_action'] = $firstAlert['action'];
            $data['alerts_has_been_grouped'] = true;
        }

        if (!empty($alert['user'])) {
            $data['creator_user_id'] = $alert['user']['user_id'];
            $data['creator_username'] = $alert['user']['username'];
        }

        if (!empty($data['content_type'])
            && !empty($data['content_id'])
            && !empty($data['content_action'])
        ) {
            $data['notification_type'] = sprintf(
                '%s_%d_%s',
                $data['content_type'],
                $data['content_id'],
                $data['content_action']
            );
        } elseif ($data['content_type'] === 'api_ping') {
            $data = array_merge($data, $alert['extra_data']);
        }

        $data['links'] = array();

        $contentLink = bdApi_Data_Helper_Core::safeBuildApiLink('notifications/content', $data);
        if (!empty($contentLink)) {
            $data['links']['content'] = $contentLink;
        }

        if (!empty($alert['user'])) {
            $data['links']['creator_avatar'] = XenForo_Template_Helper_Core::callHelper(
                'avatar',
                array($alert['user'], 'm', false, true)
            );
        }

        return $data;
    }
}
