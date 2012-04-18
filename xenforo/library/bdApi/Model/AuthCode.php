<?php
class bdApi_Model_AuthCode extends XenForo_Model
{
	public function getAuthCodeByText($authCodeText, array $fetchOptions = array())
	{
		$authCodes = $this->getAuthCodes(array('auth_code_text' => $authCodeText), $fetchOptions);
		
		return reset($authCodes);
	}
	
	protected function _getAuthCodesCustomized(array &$data, array $fetchOptions) {
		// customized processing for getAllAuthCode() should go here
	}
	
	protected function _prepareAuthCodeConditionsCustomized(array &$sqlConditions, array $conditions, array &$fetchOptions) {
		if (isset($conditions['auth_code_text']))
		{
			$sqlConditions[] = 'auth_code.auth_code_text = ' . $this->_getDb()->quote($conditions['auth_code_text']);
		}
	}
	
	protected function _prepareAuthCodeFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions) {
		// customized code goes here
	}
	
	protected function _prepareAuthCodeOrderOptionsCustomized(array &$choices, array &$fetchOptions) {
		// customized code goes here
	}
	/* Start auto-generated lines of code. Change made will be overwriten... */

	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getAuthCodes($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['auth_code_text'];
		}
		
		return $list;
	}

	public function getAuthCodeById($id, array $fetchOptions = array()) {
		$data = $this->getAuthCodes(array ('auth_code_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getAuthCodes(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareAuthCodeConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareAuthCodeOrderOptions($fetchOptions);
		$joinOptions = $this->prepareAuthCodeFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT auth_code.*
					$joinOptions[selectFields]
				FROM `xf_bdapi_auth_code` AS auth_code
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'auth_code_id');



		$this->_getAuthCodesCustomized($all, $fetchOptions);
		
		return $all;
	}
		
	public function countAuthCodes(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareAuthCodeConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareAuthCodeOrderOptions($fetchOptions);
		$joinOptions = $this->prepareAuthCodeFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_bdapi_auth_code` AS auth_code
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareAuthCodeConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('auth_code_id', 'client_id', 'expire_date', 'user_id') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				if (!empty($conditions[$intField])) {
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "auth_code.$intField IN (" . $db->quote($conditions[$intField]) . ")";
				}
			} else {
				$sqlConditions[] = "auth_code.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		$this->_prepareAuthCodeConditionsCustomized($sqlConditions, $conditions, $fetchOptions);
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareAuthCodeFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';
		
		$this->_prepareAuthCodeFetchOptionsCustomized($selectFields,  $joinTables, $fetchOptions);

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareAuthCodeOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		
		$this->_prepareAuthCodeOrderOptionsCustomized($choices, $fetchOptions);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
	


	/* End auto-generated lines of code. Feel free to make changes below */
}