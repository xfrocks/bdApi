<?php
class bdApi_Model_Token extends XenForo_Model
{	
	public function getTokenByText($tokenText, array $fetchOptions = array())
	{
		$tokens = $this->getTokens(array('token_text' => $tokenText), $fetchOptions);
		
		return reset($tokens);
	}
	
	public function getList(array $conditions = array(), array $fetchOptions = array())
	{
		$tokens = $this->getTokens($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $tokenId => $token)
		{
			$tokens[$tokenId] = $token['token_text'];
		}
		
		return $list;
	}

	public function getTokenById($tokenId, array $fetchOptions = array())
	{
		$data = $this->getTokens(array ('token_id' => $tokenId), $fetchOptions);
		
		return reset($data);
	}
	
	public function getTokens(array $conditions = array(), array $fetchOptions = array())
	{
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

		return $all;
	}
		
	public function countTokens(array $conditions = array(), array $fetchOptions = array())
	{
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
	
	public function prepareTokenConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('token_id', 'client_id', 'expire_date', 'user_id') as $columnName) {
			if (!isset($conditions[$columnName])) continue;
			
			if (is_array($conditions[$columnName]))
			{
				if (!empty($conditions[$columnName]))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "token.$columnName IN (" . $db->quote($conditions[$columnName]) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "token.$columnName = " . $db->quote($conditions[$columnName]);
			}
		}
		
		if (isset($conditions['token_text']))
		{
			$sqlConditions[] = 'token.token_text = ' . $this->_getDb()->quote($conditions['token_text']);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareTokenFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareTokenOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			
		);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}