<?php
class bdApi_Model_Token extends XenForo_Model {
	
	public function getTokenByText($tokenText, array $fetchOptions = array())
	{
		$tokens = $this->getTokens(array('token_text' => $tokenText), $fetchOptions);
		
		return reset($tokens);
	}
	
	protected function _getTokensCustomized(array &$data, array $fetchOptions) {
		// customized processing for getAllToken() should go here
	}
	
	protected function _prepareTokenConditionsCustomized(array &$sqlConditions, array $conditions, array &$fetchOptions) {
		if (isset($conditions['token_text']))
		{
			$sqlConditions[] = 'token.token_text = ' . $this->_getDb()->quote($conditions['token_text']);
		}
	}
	
	protected function _prepareTokenFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions) {
		// customized code goes here
	}
	
	protected function _prepareTokenOrderOptionsCustomized(array &$choices, array &$fetchOptions) {
		// customized code goes here
	}
	/* Start auto-generated lines of code. Change made will be overwriten... */

	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getTokens($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['token_text'];
		}
		
		return $list;
	}

	public function getTokenById($id, array $fetchOptions = array()) {
		$data = $this->getTokens(array ('token_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getTokens(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareTokenConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareTokenOrderOptions($fetchOptions);
		$joinOptions = $this->prepareTokenFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT token.*
					$joinOptions[selectFields]
				FROM `xf_bdapi_token` AS token
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'token_id');



		$this->_getTokensCustomized($all, $fetchOptions);
		
		return $all;
	}
		
	public function countTokens(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareTokenConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareTokenOrderOptions($fetchOptions);
		$joinOptions = $this->prepareTokenFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_bdapi_token` AS token
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareTokenConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('token_id', 'client_id', 'expire_date', 'user_id') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				if (!empty($conditions[$intField])) {
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "token.$intField IN (" . $db->quote($conditions[$intField]) . ")";
				}
			} else {
				$sqlConditions[] = "token.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		$this->_prepareTokenConditionsCustomized($sqlConditions, $conditions, $fetchOptions);
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareTokenFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';
		
		$this->_prepareTokenFetchOptionsCustomized($selectFields,  $joinTables, $fetchOptions);

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareTokenOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		
		$this->_prepareTokenOrderOptionsCustomized($choices, $fetchOptions);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
	


	/* End auto-generated lines of code. Feel free to make changes below */
}