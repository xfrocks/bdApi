<?php

class bdApi_ViewApi_Post_List extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        if (!empty($this->_params['_thread']['thread_id'])) {
            $subColumn = bdApi_Option::getConfig('subscriptionColumnThreadPost');

            bdApi_ViewApi_Helper_Subscription::prepareDiscoveryParams(
                $this->_params,
                $this->_response,
                bdApi_Model_Subscription::TYPE_THREAD_POST,
                $this->_params['_thread']['thread_id'],
                bdApi_Data_Helper_Core::safeBuildApiLink('posts', null, array(
                    'thread_id' => $this->_params['_thread']['thread_id'],
                    'oauth_token' => '',
                )),
                isset($this->_params['_thread'][$subColumn]) ? $this->_params['_thread'][$subColumn] : ''
            );
        }

        parent::prepareParams();
    }

}
