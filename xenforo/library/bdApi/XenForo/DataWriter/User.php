<?php

class bdApi_XenForo_DataWriter_User extends XFCP_bdApi_XenForo_DataWriter_User
{
	protected function _postDelete()
	{
		$this->getModelFromCache('bdApi_Model_Subscription')->deleteSubscriptionsForTopic(bdApi_Model_Subscription::TYPE_NOTIFICATION, $this->get('user_id'));
		
		return parent::_postDelete();
	}
}
