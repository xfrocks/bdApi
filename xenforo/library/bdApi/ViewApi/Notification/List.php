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

        call_user_func_array(array(
            'bdApi_ViewApi_Helper_Subscription',
            'prepareDiscoveryParams'
        ), array(
            &$this->_params,
            $this->_response,
            bdApi_Model_Subscription::TYPE_NOTIFICATION,
            XenForo_Visitor::getUserId(),
            XenForo_Link::buildApiLink('notifications', null, array('oauth_token' => '')),
            XenForo_Visitor::getInstance()->get('bdapi_user_notification'),
        ));

        parent::prepareParams();
    }

}
