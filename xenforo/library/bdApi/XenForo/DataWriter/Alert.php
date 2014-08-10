<?php

class bdApi_XenForo_DataWriter_Alert extends XFCP_bdApi_XenForo_DataWriter_Alert
{
	protected function _postSave()
	{
		if ($this->isInsert())
		{
			$alertModel = $this->getModelFromCache('XenForo_Model_Alert');
			$userOption = $alertModel->bdApi_getUserNotificationOption($this->get('alerted_user_id'));

			if (!empty($userOption))
			{
				$this->getModelFromCache('bdApi_Model_Subscription')->ping($userOption, 'insert', bdApi_Model_Subscription::TYPE_NOTIFICATION, $this->get('alert_id'));
			}
		}

		return parent::_postSave();
	}

}
