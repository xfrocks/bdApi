<?php

class bdApi_DataWriter_Log extends XenForo_DataWriter {

/* Start auto-generated lines of code. Change made will be overwriten... */

	protected function _getFields() {
		return array(
			'xf_bdapi_log' => array(
				'log_id' => array('type' => 'uint', 'autoIncrement' => true),
				'client_id' => array('type' => 'string', 'maxLength' => 255, 'default' => ''),
				'user_id' => array('type' => 'uint', 'required' => true),
				'ip_address' => array('type' => 'string', 'maxLength' => 50, 'default' => ''),
				'request_date' => array('type' => 'uint', 'required' => true),
				'request_method' => array('type' => 'string', 'maxLength' => 10, 'default' => 'get'),
				'request_uri' => array('type' => 'string'),
				'request_data' => array('type' => 'serialized'),
				'response_code' => array('type' => 'uint', 'required' => true),
				'response_output' => array('type' => 'serialized')
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'log_id')) {
			return false;
		}

		return array('xf_bdapi_log' => $this->_getLogModel()->getLogById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();

		foreach (array('log_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}

		return implode(' AND ', $conditions);
	}

	protected function _getLogModel() {
		return $this->getModelFromCache('bdApi_Model_Log');
	}

/* End auto-generated lines of code. Feel free to make changes below */

}