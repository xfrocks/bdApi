<?php

class bdApi_ViewApi_User_Single extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        if (!empty($this->_params['_user']['user_id'])) {
            call_user_func_array(array(
                'bdApi_ViewApi_Helper_Subscription',
                'prepareDiscoveryParams'
            ), array(
                &$this->_params,
                $this->_response,
                bdApi_Model_Subscription::TYPE_USER,
                $this->_params['_user']['user_id'],
                XenForo_Link::buildApiLink('users', $this->_params['_user'], array(OAUTH2_TOKEN_PARAM_NAME => '')),
                isset($this->_params['_user']['bdapi_user']) ? $this->_params['_user']['bdapi_user'] : '',
            ));
        }

        parent::prepareParams();
    }

}
