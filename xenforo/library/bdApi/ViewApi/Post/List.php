<?php

class bdApi_ViewApi_Post_List extends bdApi_ViewApi_Base
{
	public function prepareParams()
	{
		if (!empty($this->_params['_thread']['thread_id']))
		{
			call_user_func_array(array(
				'bdApi_ViewApi_Helper_Subscription',
				'prepareDiscoveryParams'
			), array(
				&$this->_params,
				$this->_response,
				bdApi_Model_Subscription::TYPE_THREAD_POST,
				$this->_params['_thread']['thread_id'],
				XenForo_Link::buildApiLink('posts', null, array(
					'thread_id' => $this->_params['_thread']['thread_id'],
					OAUTH2_TOKEN_PARAM_NAME => '',
				)),
				isset($this->_params['_thread']['bdapi_thread_post']) ? $this->_params['_thread']['bdapi_thread_post'] : '',
			));
		}

		return parent::prepareParams();
	}

}
