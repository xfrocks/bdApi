<?php
class bdApi_Model_Token extends XenForo_Model
{
	const FETCH_CLIENT = 0x01;
	const FETCH_USER = 0x02;
	
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
					ON (client.client_id = token.client_id)';
			}
			
			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= '
					, user.username';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user
					ON (user.user_id = token.user_id)';
			}
		}
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareTokenOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			'issue_date' => 'token.issue_date',
		);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}