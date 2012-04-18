<?php
class bdApi_DataWriter_Token extends XenForo_DataWriter {
	/* Start auto-generated lines of code. Change made will be overwriten... */
	
	protected function _getFields() {
		return array(
			'xf_bdapi_token' => array(
				'token_id' => array('type' => 'uint', 'autoIncrement' => true),
				'client_id' => array('type' => 'uint', 'required' => true),
				'token_text' => array('type' => 'string', 'required' => true, 'maxLength' => 255),
				'expire_date' => array('type' => 'uint', 'required' => true),
				'user_id' => array('type' => 'uint', 'required' => true),
				'scope' => array('type' => 'string', 'required' => true)
			)
		);
	}

	protected function _getExistingData($data) {
		if (!$id = $this->_getExistingPrimaryKey($data, 'token_id')) {
			return false;
		}

		return array('xf_bdapi_token' => $this->_getTokenModel()->getTokenById($id));
	}

	protected function _getUpdateCondition($tableName) {
		$conditions = array();
		
		foreach (array('token_id') as $field) {
			$conditions[] = $field . ' = ' . $this->_db->quote($this->getExisting($field));
		}
		
		return implode(' AND ', $conditions);
	}
	
	/**
	 * @return bdApi_Model_Token
	 */
	protected function _getTokenModel() {
		return $this->getModelFromCache('bdApi_Model_Token');
	}
	

	
	/* End auto-generated lines of code. Feel free to make changes below */
}