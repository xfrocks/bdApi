<?php

class bdApi_Deferred_DebugPingAlert extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        if (!isset($data['alert_id'])) {
            if (defined('DEFERRED_CMD')) {
                echo("data.alert_id is unset\n");
            }
            return false;
        }

        /* @var $alertModel XenForo_Model_Alert */
        $alertModel = XenForo_Model::create('XenForo_Model_Alert');
        $alert = $alertModel->getAlertById($data['alert_id']);
        if (empty($alert)) {
            if (defined('DEFERRED_CMD')) {
                echo("Alert cannot be found (alert_id={$data['alert_id']})\n");
            }
            return false;
        }

        $option = $alertModel->bdApi_getUserNotificationOption($alert['alerted_user_id']);
        if (empty($option)) {
            if (defined('DEFERRED_CMD')) {
                echo("Notification is empty (alerted_user_id={$data['alerted_user_id']})\n");
            }
            return false;
        }

        /* @var $subscriptionModel bdApi_Model_Subscription */
        $subscriptionModel = $alertModel->getModelFromCache('bdApi_Model_Subscription');
        $subscriptionModel->ping(
            $option,
            'insert',
            bdApi_Model_Subscription::TYPE_NOTIFICATION,
            $alert['alert_id']
        );

        /* @var $queueModel bdApi_Model_PingQueue */
        $queueModel = $alertModel->getModelFromCache('bdApi_Model_PingQueue');
        $hasMore = $queueModel->runQueue($targetRunTime);
        if ($hasMore) {
            return $data;
        } else {
            return false;
        }
    }
}
