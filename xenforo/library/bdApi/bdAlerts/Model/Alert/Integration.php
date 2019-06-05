<?php

class bdApi_bdAlerts_Model_Alert_Integration extends XFCP_bdApi_bdAlerts_Model_Alert_Integration
{
    public function markAlertRead(array $alert, $time = null)
    {
        $marked = parent::markAlertRead($alert, $time);
        if (!$marked) {
            return false;
        }

        if (!bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_NOTIFICATION)) {
            return $marked;
        }

        /** @var bdApi_XenForo_Model_Alert $alertModel */
        $alertModel = $this->getModelFromCache('XenForo_Model_Alert');
        $userId = $alert['alerted_user_id'];
        $userOption = $alertModel->bdApi_getUserNotificationOption($userId);
        if (empty($userOption)) {
            return $marked;
        }

        /* @var $subscriptionModel bdApi_Model_Subscription */
        $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
        $subscriptionModel->ping(
            $userOption,
            'read',
            bdApi_Model_Subscription::TYPE_NOTIFICATION,
            bdApi_AlertHandler_Ping::fakeAlert(
                $userId,
                array('read_notification_id' => $alert['alert_id'])
            )
        );

        return $marked;
    }
}
