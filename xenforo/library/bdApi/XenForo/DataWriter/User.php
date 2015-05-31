<?php

class bdApi_XenForo_DataWriter_User extends XFCP_bdApi_XenForo_DataWriter_User
{
    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xf_user_option']['bdapi_user'] = array('type' => XenForo_DataWriter::TYPE_SERIALIZED);

        return $fields;
    }

    protected function _postDelete()
    {
        $this->_bdApi_pingUser('delete');

        /* @var $subscriptionModel bdApi_Model_Subscription */
        $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
        $subscriptionModel->deleteSubscriptionsForTopic(bdApi_Model_Subscription::TYPE_NOTIFICATION, $this->get('user_id'));
        $subscriptionModel->deleteSubscriptionsForTopic(bdApi_Model_Subscription::TYPE_USER, $this->get('user_id'));

        parent::_postDelete();
    }

    protected function _postSave()
    {
        $optionLogChanges = true;
        $changeLogIgnoreFields = array();
        if (XenForo_Application::$versionId >= 1030000) {
            // user changes log is available since XenForo 1.3.0+
            // we will use its configuration when possible
            $optionLogChanges = $this->getOption(self::OPTION_LOG_CHANGES);
            $changeLogIgnoreFields = self::$changeLogIgnoreFields;
        }

        if ($this->isUpdate() AND $optionLogChanges) {
            if ($changes = $this->getNewData()) {
                $changedFields = array();

                foreach ($changes AS $table => $fields) {
                    foreach ($fields AS $field => $newValue) {
                        if (!in_array($field, $changeLogIgnoreFields, true)) {
                            $changedFields[$field] = $this->get($field);
                        }
                    }
                }

                if (!empty($changedFields)) {
                    $this->_bdApi_pingUser('update');
                }
            }
        }

        parent::_postSave();
    }

    protected function _bdApi_pingUser($action)
    {
        if (!bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_USER)) {
            // subscription for user has been disabled
            return false;
        }

        $userOption = $this->get('bdapi_user');
        if (!empty($userOption)) {
            $userOption = @unserialize($userOption);

            if (!empty($userOption)) {
                /* @var $subscriptionModel bdApi_Model_Subscription */
                $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
                $subscriptionModel->ping($userOption, $action, bdApi_Model_Subscription::TYPE_USER, $this->get('user_id'));
            }
        }

        return true;
    }

}
