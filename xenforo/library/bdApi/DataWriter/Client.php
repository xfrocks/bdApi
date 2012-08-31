<?php

class bdApi_DataWriter_Client extends XenForo_DataWriter
{
	protected function _getFields()
	{
		return array(
			'xf_bdapi_client' => array(
				'client_id' => array('type' => XenForo_DataWriter::TYPE_UINT, 'autoIncrement' => true),
				'client_secret' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
				'redirect_uri' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true),
				'name' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true, 'maxLength' => 255),
				'description' => array('type' => XenForo_DataWriter::TYPE_STRING, 'required' => true),
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
	
	/**
	 * @return bdApi_Model_Client
	 */
	protected function _getClientModel()
	{
		return $this->getModelFromCache('bdApi_Model_Client');
	}
}