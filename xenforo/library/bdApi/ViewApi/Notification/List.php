<?php

class bdApi_ViewApi_Notification_List extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        // render notification html
        $notifications = &$this->_params['notifications'];
        $templates = bdApi_ViewApi_Helper_Alert::getTemplates($this, $this->_params['_alerts'], $this->_params['_alertHandlers']);
        foreach ($notifications as $key => &$notification) {
            $notification['notification_html'] = $templates[$notification['notification_id']]['template'];
        }

        bdApi_ViewApi_Helper_Subscription::prepareDiscoveryParams(
            $this->_params,
            $this->_response,
            bdApi_Model_Subscription::TYPE_NOTIFICATION,
            XenForo_Visitor::getUserId(),
            bdApi_Data_Helper_Core::safeBuildApiLink('notifications', null, array('oauth_token' => '')),
            XenForo_Visitor::getInstance()->get(bdApi_Option::getConfig('subscriptionColumnUserNotification'))
        );

        parent::prepareParams();
    }

}
