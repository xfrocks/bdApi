<?php

class bdApi_Model_UserScope extends XenForo_Model
{
	public function updateUserScopes($token)
	{
		$userScopes = $this->getUserScopes($token['client_id'], $token['user_id']);
		$scopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($token['scope']);

		foreach ($scopes as $scope)
		{
			if (!isset($userScopes[$scope]))
			{
				$this->_getDb()->insert('xf_bdapi_user_scope', array(
					'client_id' => $token['client_id'],
					'user_id' => $token['user_id'],
					'scope' => $scope,
					'accept_date' => XenForo_Application::$time,
				));
			}
		}
	}

	public function getUserScopes($clientId, $userId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM `xf_bdapi_user_scope`
			WHERE client_id = ?
				AND user_id = ?
		', 'scope', array(
			$clientId,
			$userId
		));
	}

	public function getUserScopesForAllClients($userId)
	{
		return $this->_getDb()->fetchAll('
			SELECT user_scope.*, client.*
			FROM `xf_bdapi_user_scope` AS user_scope
			INNER JOIN `xf_bdapi_client` AS client
				ON (client.client_id = user_scope.client_id) 
			WHERE user_scope.user_id = ?
		', $userId);
	}

	public function deleteUserScope($clientId, $userId, $scope)
	{
		return $this->_getDb()->delete('xf_bdapi_user_scope', array(
			'client_id = ?' => $clientId,
			'user_id = ?' => $userId,
			'scope = ?' => $scope,
		));
	}

}
