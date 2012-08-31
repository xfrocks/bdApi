<?php
class bdApi_Model_AuthCode extends XenForo_Model
{
	public function getAuthCodeByText($authCodeText, array $fetchOptions = array())
	{
		$authCodes = $this->getAuthCodes(array('auth_code_text' => $authCodeText), $fetchOptions);
		
		return reset($authCodes);
	}

	public function getList(array $conditions = array(), array $fetchOptions = array())
	{
		$authCodes = $this->getAuthCodes($conditions, $fetchOptions);
		$list = array();
		
		foreach ($authCodes as $authCodeId => $authCode)
		{
			$list[$authCodeId] = $authCode['auth_code_text'];
		}
		
		return $list;
	}

	public function getAuthCodeById($authCodeId, array $fetchOptions = array())
	{
		$data = $this->getAuthCodes(array ('auth_code_id' => $authCodeId), $fetchOptions);
		
		return reset($data);
	}
	
	public function getAuthCodes(array $conditions = array(), array $fetchOptions = array())
	{
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

		return $all;
	}
		
	public function countAuthCodes(array $conditions = array(), array $fetchOptions = array())
	{
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
	
	public function prepareAuthCodeConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('auth_code_id', 'client_id', 'expire_date', 'user_id') as $columnName)
		{
			if (!isset($conditions[$columnName])) continue;
			
			if (is_array($conditions[$columnName]))
			{
				if (!empty($conditions[$columnName]))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "auth_code.$columnName IN (" . $db->quote($conditions[$columnName]) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "auth_code.$columnName = " . $db->quote($conditions[$columnName]);
			}
		}
		
		if (isset($conditions['auth_code_text']))
		{
			$sqlConditions[] = 'auth_code.auth_code_text = ' . $this->_getDb()->quote($conditions['auth_code_text']);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareAuthCodeFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareAuthCodeOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array(
			
		);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}