<?php
class bdApi_Model_RefreshToken extends XenForo_Model
{
	public function getRefreshTokenByText($refreshTokenText, array $fetchOptions = array())
	{
		$refreshTokens = $this->getAuthCodes(array('refresh_token_text' => $refreshTokenText), $fetchOptions);
		
		return reset($refreshTokens);
	}
	
	public function getList(array $conditions = array(), array $fetchOptions = array())
	{
		$refreshTokens = $this->getRefreshTokens($conditions, $fetchOptions);
		$list = array();
		
		foreach ($refreshTokens as $refreshTokenId => $refreshToken) {
			$list[$refreshTokenId] = $refreshToken['refresh_token_text'];
		}
		
		return $list;
	}

	public function getRefreshTokenById($refreshTokenId, array $fetchOptions = array())
	{
		$data = $this->getRefreshTokens(array ('refresh_token_id' => $refreshTokenId), $fetchOptions);
		
		return reset($data);
	}
	
	public function getRefreshTokens(array $conditions = array(), array $fetchOptions = array())
	{
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

		return $all;
	}
		
	public function countRefreshTokens(array $conditions = array(), array $fetchOptions = array())
	{
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
	
	public function prepareRefreshTokenConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('refresh_token_id', 'client_id', 'expire_date', 'user_id') as $columnName)
		{
			if (!isset($conditions[$columnName])) continue;
			
			if (is_array($conditions[$columnName]))
			{
				if (!empty($conditions[$columnName]))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "refresh_token.$columnName IN (" . $db->quote($conditions[$columnName]) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "refresh_token.$columnName = " . $db->quote($conditions[$columnName]);
			}
		}
		
		if (isset($conditions['refresh_token_text']))
		{
			$sqlConditions[] = 'refresh_token.refresh_token_text = ' . $this->_getDb()->quote($conditions['refresh_token_text']);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareRefreshTokenFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareRefreshTokenOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			
		);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}