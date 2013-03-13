<?php

class bdApi_DataWriter_Client extends XenForo_DataWriter
{
	protected function _getFields()
	{
		return array(
			'xf_bdapi_client' => array(
				'name' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
				'description' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true),	
				'client_id' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
				'client_secret' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
				'redirect_uri' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true),
				'user_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
				'options' => array('type' => XenForo_DataWriter::TYPE_SERIALIZED)
			)
		);
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