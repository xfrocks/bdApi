<?php

class bdApi_DataWriter_Client extends XenForo_DataWriter
{
	protected function _getFields()
	{
		return array('xf_bdapi_client' => array(
				'name' => array(
					'type' => XenForo_DataWriter::TYPE_STRING,
					'required' => true,
					'maxLength' => 255
				),
				'description' => array(
					'type' => XenForo_DataWriter::TYPE_STRING,
					'required' => true
				),
				'client_id' => array(
					'type' => XenForo_DataWriter::TYPE_STRING,
					'required' => true,
					'maxLength' => 255,
					'verification' => array(
						'$this',
						'_verifyClientId'
					),
				),
				'client_secret' => array(
					'type' => XenForo_DataWriter::TYPE_STRING,
					'required' => true,
					'maxLength' => 255
				),
				'redirect_uri' => array(
					'type' => XenForo_DataWriter::TYPE_STRING,
					'required' => true,
					'verification' => array(
						'$this',
						'_verifyRedirectUri'
					),
				),
				'user_id' => array(
					'type' => XenForo_DataWriter::TYPE_UINT,
					'required' => true
				),
				'options' => array('type' => XenForo_DataWriter::TYPE_SERIALIZED)
			));
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'client_id'))
		{
			return false;
		}

		return array('xf_bdapi_client' => $this->_getClientModel()->getClientById($id));
	}

	protected function _getUpdateCondition($tableName)
	{
		$conditions = array();

		foreach (array('client_id') as $field)
		{
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}

		return implode(' AND ', $conditions);
	}

	protected function _verifyClientId(&$clientId)
	{
		if ($this->isUpdate() && $clientId === $this->getExisting('client_id'))
		{
			// unchanged, always pass
			return true;
		}

		if (!preg_match('#^[a-z0-9]+$#', $clientId))
		{
			$this->error(new XenForo_Phrase('bdapi_client_id_must_be_az09'), 'client_id');
			return false;
		}

		$existingClient = $this->_getClientModel()->getClientById($clientId);
		if ($existingClient)
		{
			$this->error(new XenForo_Phrase('bdapi_client_ids_must_be_unique'), 'client_id');
			return false;
		}

		return true;
	}

	protected function _verifyRedirectUri(&$redirectUri)
	{
		if (!Zend_Uri::check($redirectUri))
		{
			$this->error(new XenForo_Phrase('bdapi_redirect_uri_must_be_valid'), 'redirect_uri');
			return false;
		}

		return true;
	}

	protected function _postDelete()
	{
		// delete associated authentication codes
		$authCodes = $this->getModelFromCache('bdApi_Model_AuthCode')->getAuthCodes(array('client_id' => $this->get('client_id')));
		foreach ($authCodes as $authCode)
		{
			$authCodeDw = XenForo_DataWriter::create('bdApi_DataWriter_AuthCode');
			$authCodeDw->setExistingData($authCode, true);
			$authCodeDw->delete();
		}

		// delete associated tokens
		$tokens = $this->getModelFromCache('bdApi_Model_Token')->getTokens(array('client_id' => $this->get('client_id')));
		foreach ($tokens as $token)
		{
			$tokenDw = XenForo_DataWriter::create('bdApi_DataWriter_Token');
			$tokenDw->setExistingData($token, true);
			$tokenDw->delete();
		}

		// delete associated refresh tokens
		$refreshTokens = $this->getModelFromCache('bdApi_Model_RefreshToken')->getRefreshTokens(array('client_id' => $this->get('client_id')));
		foreach ($refreshTokens as $refreshToken)
		{
			$refreshTokenDw = XenForo_DataWriter::create('bdApi_DataWriter_RefreshToken');
			$refreshTokenDw->setExistingData($refreshToken, true);
			$refreshTokenDw->delete();
		}

		// delete associated subscriptions
		$subscriptions = $this->getModelFromCache('bdApi_Model_Subscription')->getSubscriptions(array('client_id' => $this->get('client_id')));
		foreach ($subscriptions as $subscription)
		{
			$subscriptionDw = XenForo_DataWriter::create('bdApi_DataWriter_Subscription');
			$subscriptionDw->setExistingData($subscription, true);
			$subscriptionDw->delete();
		}

		return parent::_postDelete();
	}

	/**
	 * @return bdApi_Model_Client
	 */
	protected function _getClientModel()
	{
		return $this->getModelFromCache('bdApi_Model_Client');
	}

}
