<?php

class bdApi_bdAlerts_Model_Alert_Integration extends XFCP_bdApi_bdAlerts_Model_Alert_Integration
{
    public function markAlertRead(array $alert, $time = null)
    {
        $visitor = XenForo_Visitor::getInstance();
        $alertsUnread = $visitor['alerts_unread'];

        parent::markAlertRead($alert, $time);

        if ($visitor['alerts_unread'] !== $alertsUnread &&
            bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_NOTIFICATION)
        ) {
            /** @var bdApi_XenForo_Model_Alert $alertModel */
            $alertModel = $this->getModelFromCache('XenForo_Model_Alert');
            $userOption = $alertModel->bdApi_getUserNotificationOption($visitor['user_id']);
            if (!empty($userOption)) {
                /* @var $subscriptionModel bdApi_Model_Subscription */
                $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
                $subscriptionModel->ping(
                    $userOption,
                    'read',
                    bdApi_Model_Subscription::TYPE_NOTIFICATION,
                    bdApi_AlertHandler_Ping::fakeAlert(
                        $visitor['user_id'],
                        array('read_notification_id' => $alert['alert_id'])
                    )
                );
            }
        }
    }
}
