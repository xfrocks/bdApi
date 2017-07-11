<?php

class bdApi_ViewApi_User_Single extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        if (!empty($this->_params['_user']['user_id'])) {
            $subColumn = bdApi_Option::getConfig('subscriptionColumnUser');

            bdApi_ViewApi_Helper_Subscription::prepareDiscoveryParams(
                $this->_params,
                $this->_response,
                bdApi_Model_Subscription::TYPE_USER,
                $this->_params['_user']['user_id'],
                bdApi_Data_Helper_Core::safeBuildApiLink('users', $this->_params['_user'], array('oauth_token' => '')),
                isset($this->_params['_user'][$subColumn]) ? $this->_params['_user'][$subColumn] : ''
            );
        }

        parent::prepareParams();
    }
}
