<?php
class bdApi_DataWriter_AuthCode extends XenForo_DataWriter {
	/* Start auto-generated lines of code. Change made will be overwriten... */
	
	protected function _getFields() {
		return array(
			'xf_bdapi_auth_code' => array(
				'auth_code_id' => array('type' => 'uint', 'autoIncrement' => true),
				'client_id' => array('type' => 'uint', 'required' => true),
				'auth_code_text' => array('type' => 'string', 'required' => true, 'maxLength' => 255),
				'redirect_uri' => array('type' => 'string', 'required' => true),
				'expire_date' => array('type' => 'uint', 'required' => true),
				'user_id' => array('type' => 'uint', 'required' => true),
				'scope' => array('type' => 'string', 'required' => true)
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'auth_code_id')) {
			return false;
		}

		return array('xf_bdapi_auth_code' => $this->_getAuthCodeModel()->getAuthCodeById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('auth_code_id') as $field) {
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
	

	
	/* End auto-generated lines of code. Feel free to make changes below */
}