<?php

class bdApi_XenForo_DataWriter_Alert extends XFCP_bdApi_XenForo_DataWriter_Alert
{
    protected function _postSave()
    {
        if ($this->isInsert()
            && bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_NOTIFICATION)
        ) {
            /* @var $subscriptionModel bdApi_Model_Subscription */
            $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
            $alertedUserId = $this->get('alerted_user_id');

            if ($alertedUserId > 0) {
                /* @var $alertModel bdApi_XenForo_Model_Alert */
                $alertModel = $this->getModelFromCache('XenForo_Model_Alert');
                $option = $alertModel->bdApi_getUserNotificationOption($this->get('alerted_user_id'));
            } else {
                $option = $subscriptionModel->getClientSubscriptionsData();
            }

            if (!empty($option)) {
                $subscriptionModel->ping($option, 'insert', bdApi_Model_Subscription::TYPE_NOTIFICATION, $this->get('alert_id'));
            }
        }

        parent::_postSave();
    }

}
