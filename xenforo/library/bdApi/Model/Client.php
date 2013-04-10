<?php
class bdApi_Model_Client extends XenForo_Model
{
	private $_clients = array();

	public function signApiData($client, array &$data)
	{
		$str = '';

		$keys = array_keys($data);
		asort($keys);
		foreach ($keys as $key)
		{
			if ($key == 'signature') continue; // ?!
			
			$str .= sprintf('%s=%s&', $key, $data[$key]);
		}
		$str .= $client['client_secret'];

		$data['signature'] = md5($str);
	}

	public function canAutoAuthorize($client, $scopes)
	{
		$scopeArray = bdApi_Template_Helper_Core::getInstance()->scopeSplit($scopes);

		foreach ($scopeArray as $scope)
		{
			if (empty($client['options']['auto_authorize'][$scope]))
			{
				// at least one scope requested is missing
				// CANNOT auto authorize this set of scopes
				return false;
			}
		}

		return true;
	}
	
	public function generateClientId()
	{
		do
		{
			$clientId = $this->_generateRandomString(bdApi_Option::get('keyLength'));
			$client = $this->getClientById($clientId);
		}
		while (!empty($client));
		
		return $clientId;
	}
	
	public function generateClientSecret()
	{
		return $this->_generateRandomString(bdApi_Option::get('secretLength'));
	}
	
	protected function _generateRandomString($length)
	{
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$randStr = '';
		
		for ($i = 0; $i < $length; $i++)
		{
			$randStr .= $chars[rand(0, strlen($chars) - 1)];
		}
		
		return $randStr;
	}
	
	public function verifySecret(array $client, $secret)
	{
		return $client['client_secret'] == $secret;
	}

	public function getList(array $conditions = array(), array $fetchOptions = array())
	{
		$clients = $this->getClients($conditions, $fetchOptions);
		$list = array();
		
		foreach ($clients as $clientId => $client)
		{
			$list[$clientId] = $client['name'];
		}
		
		return $list;
	}

	public function getClientById($clientId, array $fetchOptions = array())
	{
		if (empty($fetchOptions))
		{
			foreach ($this->_clients as $client)
			{
				if ($client['client_id'] == $clientId)
				{
					// try to get previously cached data
					return $client;
				}
			}
		}
		
		$data = $this->getClients(array ('client_id' => $clientId), $fetchOptions);
		$client = reset($data);
		$this->_clients[] = $data;
		
		return $client;
	}
	
	public function getClients(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareClientConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareClientOrderOptions($fetchOptions);
		$joinOptions = $this->prepareClientFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT client.*
					$joinOptions[selectFields]
				FROM `xf_bdapi_client` AS client
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'client_id');

		foreach ($all as &$client)
		{
			$client['options'] = @unserialize($client['options']);
			if (empty($client['options'])) $client['options'] = array();
		}

		return $all;
	}
		
	public function countClients(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareClientConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareClientOrderOptions($fetchOptions);
		$joinOptions = $this->prepareClientFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_bdapi_client` AS client
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareClientConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('client_id', 'user_id') as $columnName)
		{
			if (!isset($conditions[$columnName])) continue;
			
			if (is_array($conditions[$columnName]))
			{
				if (!empty($conditions[$columnName]))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "client.$columnName IN (" . $db->quote($conditions[$columnName]) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "client.$columnName = " . $db->quote($conditions[$columnName]);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareClientFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareClientOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}