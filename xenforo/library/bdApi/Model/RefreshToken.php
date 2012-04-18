<?php
class bdApi_Model_RefreshToken extends XenForo_Model
{
	public function getRefreshTokenByText($refreshTokenText, array $fetchOptions = array())
	{
		$refreshTokens = $this->getAuthCodes(array('refresh_token_text' => $refreshTokenText), $fetchOptions);
		
		return reset($refreshTokens);
	}
	
	protected function _getRefreshTokensCustomized(array &$data, array $fetchOptions) {
		// customized processing for getAllRefreshToken() should go here
	}
	
	protected function _prepareRefreshTokenConditionsCustomized(array &$sqlConditions, array $conditions, array &$fetchOptions) {
		if (isset($conditions['refresh_token_text']))
		{
			$sqlConditions[] = 'refresh_token.refresh_token_text = ' . $this->_getDb()->quote($conditions['refresh_token_text']);
		}
	}
	
	protected function _prepareRefreshTokenFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions) {
		// customized code goes here
	}
	
	protected function _prepareRefreshTokenOrderOptionsCustomized(array &$choices, array &$fetchOptions) {
		// customized code goes here
	}
	/* Start auto-generated lines of code. Change made will be overwriten... */

	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getRefreshTokens($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['refresh_token_text'];
		}
		
		return $list;
	}

	public function getRefreshTokenById($id, array $fetchOptions = array()) {
		$data = $this->getRefreshTokens(array ('refresh_token_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getRefreshTokens(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareRefreshTokenConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareRefreshTokenOrderOptions($fetchOptions);
		$joinOptions = $this->prepareRefreshTokenFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT refresh_token.*
					$joinOptions[selectFields]
				FROM `xf_bdapi_refresh_token` AS refresh_token
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'refresh_token_id');



		$this->_getRefreshTokensCustomized($all, $fetchOptions);
		
		return $all;
	}
		
	public function countRefreshTokens(array $conditions = array(), array $fetchOptions = array()) {
		$whereConditions = $this->prepareRefreshTokenConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareRefreshTokenOrderOptions($fetchOptions);
		$joinOptions = $this->prepareRefreshTokenFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_bdapi_refresh_token` AS refresh_token
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareRefreshTokenConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('refresh_token_id', 'client_id', 'expire_date', 'user_id') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				if (!empty($conditions[$intField])) {
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "refresh_token.$intField IN (" . $db->quote($conditions[$intField]) . ")";
				}
			} else {
				$sqlConditions[] = "refresh_token.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		$this->_prepareRefreshTokenConditionsCustomized($sqlConditions, $conditions, $fetchOptions);
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareRefreshTokenFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';
		
		$this->_prepareRefreshTokenFetchOptionsCustomized($selectFields,  $joinTables, $fetchOptions);

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareRefreshTokenOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		
		$this->_prepareRefreshTokenOrderOptionsCustomized($choices, $fetchOptions);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
	


	/* End auto-generated lines of code. Feel free to make changes below */
}