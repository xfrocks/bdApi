<?php

class bdApi_Model_Log extends XenForo_Model
{
	public function logRequest($responseCode, array $responseOutput)
	{
		$session = XenForo_Application::getSession();
		$visitor = XenForo_Visitor::getInstance();

		$dw = XenForo_DataWriter::create('bdApi_DataWriter_Log');
		$dw->set('client_id', $session->getOAuthClientId());
		$dw->set('user_id', $visitor->get('user_id'));
		$dw->set('ip_address', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
		$dw->set('request_date', XenForo_Application::$time);
		$dw->set('request_method', isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : '');
		$dw->set('request_uri', $this->_getRequestUri());
		$dw->set('request_data', $this->_filterData($_REQUEST));
		$dw->set('response_code', $responseCode);
		$dw->set('response_output', $this->_filterData($responseOutput));

		$dw->save();
	}

	public function getList(array $conditions = array(), array $fetchOptions = array())
	{
		$data = $this->getLogs($conditions, $fetchOptions);
		$list = array();

		foreach ($data as $id => $row) {
			$list[$id] = $row['client_id'];
		}

		return $list;
	}

	public function getLogById($id, array $fetchOptions = array())
	{
		$data = $this->getLogs(array ('log_id' => $id), $fetchOptions);

		return reset($data);
	}

	public function getLogs(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareLogConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareLogOrderOptions($fetchOptions);
		$joinOptions = $this->prepareLogFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT log.*
				$joinOptions[selectFields]
				FROM `xf_bdapi_log` AS log
				$joinOptions[joinTables]
				WHERE $whereConditions
				$orderClause
				", $limitOptions['limit'], $limitOptions['offset']
		), 'log_id');

		return $all;
	}

	public function countLogs(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareLogConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareLogOrderOptions($fetchOptions);
		$joinOptions = $this->prepareLogFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
				SELECT COUNT(*)
				FROM `xf_bdapi_log` AS log
				$joinOptions[joinTables]
				WHERE $whereConditions
				");
	}

	public function prepareLogConditions(array $conditions = array(), array $fetchOptions = array())
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (isset($conditions['log_id']))
		{
			if (is_array($conditions['log_id']))
			{
				if (!empty($conditions['log_id']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "log.log_id IN (" . $db->quote($conditions['log_id']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "log.log_id = " . $db->quote($conditions['log_id']);
			}
		}

		if (isset($conditions['client_id']))
		{
			if (is_array($conditions['client_id']))
			{
				if (!empty($conditions['client_id']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "log.client_id IN (" . $db->quote($conditions['client_id']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "log.client_id = " . $db->quote($conditions['client_id']);
			}
		}

		if (isset($conditions['user_id']))
		{
			if (is_array($conditions['user_id']))
			{
				if (!empty($conditions['user_id']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "log.user_id IN (" . $db->quote($conditions['user_id']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "log.user_id = " . $db->quote($conditions['user_id']);
			}
		}

		if (isset($conditions['ip_address']))
		{
			if (is_array($conditions['ip_address']))
			{
				if (!empty($conditions['ip_address']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "log.ip_address IN (" . $db->quote($conditions['ip_address']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "log.ip_address = " . $db->quote($conditions['ip_address']);
			}
		}

		if (isset($conditions['request_date']))
		{
			if (is_array($conditions['request_date']))
			{
				if (!empty($conditions['request_date']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "log.request_date IN (" . $db->quote($conditions['request_date']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "log.request_date = " . $db->quote($conditions['request_date']);
			}
		}

		if (isset($conditions['request_method']))
		{
			if (is_array($conditions['request_method']))
			{
				if (!empty($conditions['request_method']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "log.request_method IN (" . $db->quote($conditions['request_method']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "log.request_method = " . $db->quote($conditions['request_method']);
			}
		}

		if (isset($conditions['response_code']))
		{
			if (is_array($conditions['response_code']))
			{
				if (!empty($conditions['response_code']))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "log.response_code IN (" . $db->quote($conditions['response_code']) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "log.response_code = " . $db->quote($conditions['response_code']);
			}
		}

		return $this->getConditionsForClause($sqlConditions);
	}

	public function prepareLogFetchOptions(array $fetchOptions = array())
	{
		$selectFields = '';
		$joinTables = '';

		return array(
				'selectFields' => $selectFields,
				'joinTables'   => $joinTables
		);
	}

	public function prepareLogOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
	{
		$choices = array(
		);

		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
	
	protected function _getRequestUri()
	{
		$requestPaths = XenForo_Application::get('requestPaths');
		$fullUri = $requestPaths['fullUri'];
		
		$pos = strpos($fullUri, '?');
		if ($pos !== false)
		{
			$fullUri = substr($fullUri, 0, $pos);
		}
		
		return $fullUri;
	}

	protected function _filterData(array &$data)
	{
		static $whitelistedKeys = array(
				'error',
				'message',
		);

		$filtered = array();

		foreach ($data as $key => &$value)
		{
			if (is_array($value))
			{
				$filtered[$key] = $this->_filterData($value);
			}
			else
			{
				if (in_array($key, $whitelistedKeys))
				{
					$filtered[$key] = strval($value);
				}
				else
				{
					$filtered[$key] = '*';
				}
			}
		}
 
		return $filtered;
	}
}