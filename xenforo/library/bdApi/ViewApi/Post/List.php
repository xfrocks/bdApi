<?php

class bdApi_ViewApi_Post_List extends bdApi_ViewApi_Base
{
	public function prepareParams()
	{
		if (!empty($this->_params['_thread']['thread_id']))
		{
			// subscription discovery
			$hubLink = bdApi_Link::buildApiLink('subscriptions', null, array(
				'hub.topic' => bdApi_Model_Subscription::getTopic(bdApi_Model_Subscription::TYPE_THREAD_POST, $this->_params['_thread']['thread_id']),
				OAUTH2_TOKEN_PARAM_NAME => '',
			));
			$this->_response->setHeader('Link', sprintf('<%s>; rel=hub', $hubLink));
			$selfLink = bdApi_Link::buildApiLink('posts', null, array(
				'thread_id' => $this->_params['_thread']['thread_id'],
				OAUTH2_TOKEN_PARAM_NAME => '',
			));
			$this->_response->setHeader('Link', sprintf('<%s>; rel=self', $selfLink));
		}

		return parent::prepareParams();
	}

}
