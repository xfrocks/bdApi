<?php
class bdApi_Model_RefreshToken extends XenForo_Model
{
	const FETCH_CLIENT = 0x01;
	const FETCH_USER = 0x02;
	
	public function pruneExpired()
	{
		$this->_getDb()->query('
			DELETE FROM `xf_bdapi_refresh_token`
			WHERE expire_date > 0
				AND expire_date < ?
		', array(XenForo_Application::$time));
	}
	
	public function getRefreshTokenByText($refreshTokenText, array $fetchOptions = array())
	{
		$refreshTokens = $this->getRefreshTokens(array('refresh_token_text' => $refreshTokenText), $fetchOptions);
		
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
		
		if (!empty($fetchOptions['join']))
		{
			if ($fetchOptions['join'] & self::FETCH_CLIENT)
			{
				$selectFields .= '
					, client.name AS client_name
					, client.description AS client_description
					, client.redirect_uri AS client_redirect_uri';
				$joinTables .= '
					LEFT JOIN `xf_bdapi_client` AS client
					ON (client.client_id = refresh_token.client_id)';
			}
			
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= '
					, user.username';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user
					ON (user.user_id = refresh_token.user_id)';
			}
		}
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareRefreshTokenOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			'issue_date' => 'refresh_token.issue_date',
		);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}