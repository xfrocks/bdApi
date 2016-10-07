<?php

class bdApi_ViewApi_User_List extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            if (!!bdApi_Data_Helper_Core::safeGetSession()->getOAuthClientOption('allow_user_0_subscription')) {
                bdApi_ViewApi_Helper_Subscription::prepareDiscoveryParams($this->_params,
                    $this->_response, bdApi_Model_Subscription::TYPE_USER, 0,
                    '', XenForo_Application::getSimpleCacheData(bdApi_Model_Subscription::TYPE_USER_0_SIMPLE_CACHE));
            }
        }

        parent::prepareParams();
    }

}
