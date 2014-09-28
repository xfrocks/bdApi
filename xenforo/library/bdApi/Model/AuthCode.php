<?php
class bdApi_Model_AuthCode extends XenForo_Model
{
	const FETCH_CLIENT = 0x01;
	const FETCH_USER = 0x02;

	public function deleteAuthCodes($clientId, $userId)
	{
		return $this->_getDb()->delete('xf_bdapi_auth_code', array(
			'client_id = ?' => $clientId,
			'user_id = ?' => $userId,
		));
	}

	public function pruneExpired()
	{
		$this->_getDb()->query('
			DELETE FROM `xf_bdapi_auth_code`
			WHERE expire_date > 0
				AND expire_date < ?
		', array(XenForo_Application::$time));
	}

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
		$data = $this->getAuthCodes(array('auth_code_id' => $authCodeId), $fetchOptions);

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
			", $limitOptions['limit'], $limitOptions['offset']), 'auth_code_id');

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
			if (!isset($conditions[$columnName]))
			{
				continue;
			}

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

		if (isset($conditions['expired']))
		{
			if ($conditions['expired'])
			{
				$sqlConditions[] = 'auth_code.expire_date > 0';
				$sqlConditions[] = 'auth_code.expire_date < ' . XenForo_Application::$time;
			}
			else
			{
				$sqlConditions[] = 'auth_code.expire_date = 0 OR auth_code.expire_date > ' . XenForo_Application::$time;
			}
		}

		if (!empty($conditions['filter']))
		{
			if (is_array($conditions['filter']))
			{
				$filterQuoted = XenForo_Db::quoteLike($conditions['filter'][0], $conditions['filter'][1], $db);
			}
			else
			{
				$filterQuoted = XenForo_Db::quoteLike($conditions['filter'], 'lr', $db);
			}

			$sqlConditions[] = sprintf('auth_code.auth_code_text LIKE %1$s', $filterQuoted);
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareAuthCodeFetchOptions(array $fetchOptions)
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
					ON (client.client_id = auth_code.client_id)';
			}

			if ($fetchOptions['join'] & self::FETCH_USER)
			{
				$selectFields .= '
					, user.username';
				$joinTables .= '
					LEFT JOIN `xf_user` AS user
					ON (user.user_id = auth_code.user_id)';
			}
		}

		return array(
			'selectFields' => $selectFields,
			'joinTables' => $joinTables
		);
	}

	public function prepareAuthCodeOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
	{
		$choices = array('issue_date' => 'auth_code.issue_date');

		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

}
