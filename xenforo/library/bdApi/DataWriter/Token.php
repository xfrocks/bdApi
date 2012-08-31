<?php

class bdApi_DataWriter_Token extends XenForo_DataWriter
{
	protected function _getFields()
	{
		return array(
			'xf_bdapi_token' => array(
				'token_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
				'client_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
				'token_text' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
				'expire_date' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
				'user_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'required' => true),
				'scope' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true)
			)
		);
	}

	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'token_id')) {
			return false;
		}

		return array('xf_bdapi_token' => $this->_getTokenModel()->getTokenById($id));
	}

	protected function _getUpdateCondition($tableName)
	{
		$conditions = array();
		
		foreach (array('token_id') as $field)
		{
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	/**
	 * @return bdApi_Model_Token
	 */
	protected function _getTokenModel()
	{
		return $this->getModelFromCache('bdApi_Model_Token');
	}
}