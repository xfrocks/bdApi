<?php

class bdApi_XenForo_DataWriter_DiscussionMessage_Post extends XFCP_bdApi_XenForo_DataWriter_DiscussionMessage_Post
{
    protected $_trackPostOrigin = '';

    public function bdApi_setOrigin($clientId)
    {
        if (empty($this->_trackPostOrigin)) {
            return false;
        }

        return $this->set($this->_trackPostOrigin, $clientId);
    }

    protected function _getFields()
    {
        $fields = parent::_getFields();

        $this->_trackPostOrigin = bdApi_Option::get('trackPostOrigin');
        if (!empty($this->_trackPostOrigin)) {
            $fields['xf_post'][$this->_trackPostOrigin] = array(
                'type' => XenForo_DataWriter::TYPE_STRING,
                'maxLength' => 255,
                'default' => '',
            );
        }

        return $fields;
    }

    protected function _messagePostSave()
    {
        if ($this->isInsert()) {
            if ($this->get('message_state') == 'visible') {
                $this->_bdApi_pingThreadPost('insert');
            }
        } elseif ($this->isChanged('message_state')) {
            if ($this->get('message_state') == 'visible') {
                $this->_bdApi_pingThreadPost('insert');
            } elseif ($this->getExisting('message_state') == 'visible') {
                $this->_bdApi_pingThreadPost('delete');
            }
        } else {
            $this->_bdApi_pingThreadPost('update');
        }

        parent::_messagePostSave();
    }

    protected function _messagePostDelete()
    {
        if ($this->getExisting('message_state') == 'visible') {
            $this->_bdApi_pingThreadPost('delete');
        }

        parent::_messagePostDelete();
    }

    protected function _bdApi_pingThreadPost($action)
    {
        if (!bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_THREAD_POST)) {
            // subscription for thread post has been disabled
            return false;
        }

        $thread = $this->getDiscussionData();
        if (!empty($thread['bdapi_thread_post'])) {
            $threadOption = @unserialize($thread['bdapi_thread_post']);

            if (!empty($threadOption)) {
                /* @var $subscriptionModel bdApi_Model_Subscription */
                $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
                $subscriptionModel->ping($threadOption, $action, bdApi_Model_Subscription::TYPE_THREAD_POST, $this->get('post_id'));
            }
        }

        return true;
    }

}
