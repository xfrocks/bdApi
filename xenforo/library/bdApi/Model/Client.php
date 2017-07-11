<?php

class bdApi_Model_Client extends XenForo_Model
{
    private $_clients = array();

    public function signApiData($client, array &$data)
    {
        $str = '';

        $keys = array_keys($data);
        asort($keys);
        foreach ($keys as $key) {
            if ($key == 'signature') {
                // do not include existing signature when signing
                // it's safe to run this method more than once with the same $data
                continue;
            }

            if (is_array($data[$key])) {
                // do not support array in signing for now
                unset($data[$key]);
                continue;
            }

            if (is_bool($data[$key])) {
                // strval(true) = 1 while strval(false) = 0
                // so we will normalize bool to int before the strval
                $data[$key] = ($data[$key] ? 1 : 0);
            }

            $str .= sprintf('%s=%s&', $key, $data[$key]);
        }
        $str .= $client['client_secret'];

        $data['signature'] = md5($str);
    }

    public function canAutoAuthorize($client, $scopes)
    {
        $scopeArray = bdApi_Template_Helper_Core::getInstance()->scopeSplit($scopes);

        foreach ($scopeArray as $scope) {
            if (empty($client['options']['auto_authorize'][$scope])) {
                // at least one scope requested is missing
                // CANNOT auto authorize this set of scopes
                return false;
            }
        }

        return true;
    }

    public function generateClientId()
    {
        do {
            $clientId = $this->_generateRandomString(bdApi_Option::get('keyLength'));
            $client = $this->getClientById($clientId);
        } while (!empty($client));

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

        for ($i = 0; $i < $length; $i++) {
            $randStr .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $randStr;
    }

    public function verifySecret(array $client, $secret)
    {
        return $client['client_secret'] == $secret;
    }

    public function getWhitelistedRedirectUri(array $client, $requestedRedirectUri)
    {
        if (empty($client['options']['whitelisted_domains'])) {
            return false;
        }

        if (empty($requestedRedirectUri) OR !is_string($requestedRedirectUri)) {
            return false;
        }
        $parsed = @parse_url($requestedRedirectUri);
        if (empty($parsed['scheme']) OR empty($parsed['host'])) {
            return false;
        }

        $domains = explode("\n", $client['options']['whitelisted_domains']);
        foreach ($domains as $domain) {
            if (empty($domain)) {
                continue;
            }

            $pattern = '#^';
            for ($i = 0, $l = utf8_strlen($domain); $i < $l; $i++) {
                $char = utf8_substr($domain, $i, 1);
                if ($char === '*') {
                    $pattern .= '.+?';
                } else {
                    $pattern .= preg_quote($char, '#');
                }
            }
            $pattern .= '$#';
            if (preg_match($pattern, $parsed['host'])) {
                // we have found a match
                return $requestedRedirectUri;
            }
        }

        return false;
    }

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $clients = $this->getClients($conditions, $fetchOptions);
        $list = array();

        foreach ($clients as $clientId => $client) {
            $list[$clientId] = $client['name'];
        }

        return $list;
    }

    public function getClientById($clientId, array $fetchOptions = array())
    {
        if (empty($fetchOptions)) {
            foreach ($this->_clients as $client) {
                if ($client['client_id'] == $clientId) {
                    // try to get previously cached data
                    return $client;
                }
            }
        }

        $data = $this->getClients(array('client_id' => $clientId), $fetchOptions);
        if (empty($data)) {
            return null;
        }

        $client = reset($data);
        $this->_clients[$client['client_id']] = $client;

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
			", $limitOptions['limit'], $limitOptions['offset']), 'client_id');

        foreach ($all as &$client) {
            $client['options'] = @unserialize($client['options']);
            if (empty($client['options'])) {
                $client['options'] = array();
            }
        }

        return $all;
    }

    public function countClients(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareClientConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareClientFetchOptions($fetchOptions);

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

        foreach (array('client_id', 'user_id') as $columnName) {
            if (!isset($conditions[$columnName])) {
                continue;
            }

            if (is_array($conditions[$columnName])) {
                if (!empty($conditions[$columnName])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client.$columnName IN (" . $db->quote($conditions[$columnName]) . ")";
                }
            } else {
                $sqlConditions[] = "client.$columnName = " . $db->quote($conditions[$columnName]);
            }
        }

        if (!empty($conditions['filter'])) {
            if (is_array($conditions['filter'])) {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'][0], $conditions['filter'][1], $db);
            } else {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'], 'lr', $db);
            }

            $sqlConditions[] = sprintf('(client.name LIKE %1$s OR client.redirect_uri LIKE %1$s)', $filterQuoted);
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareClientFetchOptions(array $fetchOptions)
    {
        $selectFields = '';
        $joinTables = '';

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareClientOrderOptions(array &$fetchOptions, $defaultOrderSql = '')
    {
        $choices = array();

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }
}
