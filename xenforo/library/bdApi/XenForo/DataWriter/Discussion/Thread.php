<?php

class bdApi_XenForo_DataWriter_Discussion_Thread_Base extends XFCP_bdApi_XenForo_DataWriter_Discussion_Thread
{
    protected function _bdApi_discussionPostDelete()
    {
        /* @var $subscriptionModel bdApi_Model_Subscription */
        $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
        $subscriptionModel->deleteSubscriptionsForTopic(bdApi_Model_Subscription::TYPE_THREAD_POST, $this->get('thread_id'));
    }

}

if (XenForo_Application::$versionId >= 1020000) {
    class bdApi_XenForo_DataWriter_Discussion_Thread extends bdApi_XenForo_DataWriter_Discussion_Thread_Base
    {
        protected function _discussionPostDelete()
        {
            $this->_bdApi_discussionPostDelete();

            parent::_discussionPostDelete();
        }

    }

} else {
    class bdApi_XenForo_DataWriter_Discussion_Thread extends bdApi_XenForo_DataWriter_Discussion_Thread_Base
    {
        protected function _discussionPostDelete(array $messages)
        {
            $this->_bdApi_discussionPostDelete();

            /** @noinspection PhpMethodParametersCountMismatchInspection */
            parent::_discussionPostDelete($messages);
        }

    }

}
