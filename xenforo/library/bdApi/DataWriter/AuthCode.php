<?php

class bdApi_DataWriter_AuthCode extends XenForo_DataWriter
{
	protected function _getFields()
	{
		return array(
			'xf_bdapi_auth_code' => array(
				'auth_code_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
				'client_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
				'auth_code_text' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
				'redirect_uri' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true),
				'expire_date' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
				'user_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
				'scope' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true)
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'auth_code_id'))
		{
			return false;
		}

		return array('xf_bdapi_auth_code' => $this->_getAuthCodeModel()->getAuthCodeById($id));
	}

	protected function _getUpdateCondition($tableName)
	{
		$conditions = array();
		
		foreach (array('auth_code_id') as $field)
		{
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	/**
	 * @return bdApi_Model_AuthCode
	 */
	protected function _getAuthCodeModel() {
		return $this->getModelFromCache('bdApi_Model_AuthCode');
	}
}