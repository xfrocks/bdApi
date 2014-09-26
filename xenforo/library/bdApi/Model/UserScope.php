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

}
