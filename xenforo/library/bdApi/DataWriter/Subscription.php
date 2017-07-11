<?php

class bdApi_DataWriter_Subscription extends XenForo_DataWriter
{
    const OPTION_UPDATE_CALLBACKS = 'updateCallbacks';

    protected function _getDefaultOptions()
    {
        return array(self::OPTION_UPDATE_CALLBACKS => true);
    }

    protected function _getFields()
    {
        return array(
            'xf_bdapi_subscription' => array(
                'subscription_id' => array(
                    'type' => XenForo_DataWriter::TYPE_UINT,
                    'autoIncrement' => true
                ),
                'client_id' => array(
                    'type' => XenForo_DataWriter::TYPE_STRING,
                    'required' => true,
                    'maxLength' => 255
                ),
                'callback' => array(
                    'type' => XenForo_DataWriter::TYPE_STRING,
                    'required' => true
                ),
                'topic' => array(
                    'type' => XenForo_DataWriter::TYPE_STRING,
                    'default' => '',
                    'maxLength' => 255
                ),
                'subscribe_date' => array(
                    'type' => XenForo_DataWriter::TYPE_UINT,
                    'required' => true
                ),
                'expire_date' => array(
                    'type' => XenForo_DataWriter::TYPE_UINT,
                    'required' => true,
                    'default' => 0
                ),
            )
        );
    }

    protected function _getExistingData($data)
    {
        if (!$id = $this->_getExistingPrimaryKey($data, 'subscription_id')) {
            return false;
        }

        return array('xf_bdapi_subscription' => $this->_getSubscriptionModel()->getSubscriptionById($id));
    }

    protected function _getUpdateCondition($tableName)
    {
        $conditions = array();

        foreach (array('subscription_id') as $field) {
            $conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
        }

        return implode(' AND ', $conditions);
    }

    protected function _postSave()
    {
        if ($this->getOption(self::OPTION_UPDATE_CALLBACKS)) {
            $this->_getSubscriptionModel()->updateCallbacksForTopic($this->get('topic'));
        }

        parent::_postSave();
    }

    protected function _postDelete()
    {
        if ($this->getOption(self::OPTION_UPDATE_CALLBACKS)) {
            $this->_getSubscriptionModel()->updateCallbacksForTopic($this->get('topic'));
        }

        parent::_postDelete();
    }

    /**
     * @return bdApi_Model_Subscription
     */
    protected function _getSubscriptionModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdApi_Model_Subscription');
    }
}
