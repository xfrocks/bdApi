<?php
class bdApi_Model_Client extends XenForo_Model {
	
	public function verifySecret(array $client, $secret)
	{
		return $client['client_secret'] == $secret;
		// TODO: switch to use hashSecret
		// return $client['client_secret'] == $this->hashSecret($secret);
	}
	
	public function hashSecret($secret)
	{
		return md5(str_repeat(md5($secret), 2));
	}
	
	protected function _getClientsCustomized(array &$data, array $fetchOptions) {
		// customized processing for getAllClient() should go here
	}
	
	protected function _prepareClientConditionsCustomized(array &$sqlConditions, array $conditions, array &$fetchOptions) {
		// customized code goes here
	}
	
	protected function _prepareClientFetchOptionsCustomized(&$selectFields, &$joinTables, array $fetchOptions) {
		// customized code goes here
	}
	
	protected function _prepareClientOrderOptionsCustomized(array &$choices, array &$fetchOptions) {
		// customized code goes here
	}
	/* Start auto-generated lines of code. Change made will be overwriten... */

	public function getList(array $conditions = array(), array $fetchOptions = array()) {
		$data = $this->getClients($conditions, $fetchOptions);
		$list = array();
		
		foreach ($data as $id => $row) {
			$list[$id] = $row['name'];
		}
		
		return $list;
	}

	public function getClientById($id, array $fetchOptions = array()) {
		$data = $this->getClients(array ('client_id' => $id), $fetchOptions);
		
		return reset($data);
	}
	
	public function getClients(array $conditions = array(), array $fetchOptions = array()) {
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



		$this->_getClientsCustomized($all, $fetchOptions);
		
		return $all;
	}
		
	public function countClients(array $conditions = array(), array $fetchOptions = array()) {
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
	
	public function prepareClientConditions(array $conditions, array &$fetchOptions) {
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('client_id', 'user_id') as $intField) {
			if (!isset($conditions[$intField])) continue;
			
			if (is_array($conditions[$intField])) {
				$sqlConditions[] = "client.$intField IN (" . $db->quote($conditions[$intField]) . ")";
			} else {
				$sqlConditions[] = "client.$intField = " . $db->quote($conditions[$intField]);
			}
		}
		
		$this->_prepareClientConditionsCustomized($sqlConditions, $conditions, $fetchOptions);
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareClientFetchOptions(array $fetchOptions) {
		$selectFields = '';
		$joinTables = '';
		
		$this->_prepareClientFetchOptionsCustomized($selectFields,  $joinTables, $fetchOptions);

		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareClientOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		
		$this->_prepareClientOrderOptionsCustomized($choices, $fetchOptions);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
	


	/* End auto-generated lines of code. Feel free to make changes below */
}