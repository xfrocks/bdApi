<?php

class bdApi_XenForo_DataWriter_Discussion_Thread extends XFCP_bdApi_XenForo_DataWriter_Discussion_Thread
{
	protected function _discussionPostDelete()
	{
		$this->getModelFromCache('bdApi_Model_Subscription')->deleteSubscriptionsForTopic(bdApi_Model_Subscription::TYPE_THREAD_POST, $this->get('thread_id'));

		return parent::_discussionPostDelete();
	}

}
