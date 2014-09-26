<?php

class bdApiConsumer_XenForo_Model_UserExternal extends XFCP_bdApiConsumer_XenForo_Model_UserExternal
{
	public function bdApiConsumer_getProviderCode(array $provider)
	{
		return 'bdapi_' . $provider['code'];
	}

	public function bdApiConsumer_getUserProfileField()
	{
		return 'bdapiconsumer_unused';
	}

	public function bdApiConsumer_syncUpOnRegistration(XenForo_DataWriter_User $userDw, $externalToken, array $externalVisitor)
	{
		// TODO
	}

	public function bdApiConsumer_getAccessTokenFromAuth(array $provider, array $auth)
	{
		if (!is_array($auth['extra_data']))
		{
			$auth['extra_data'] = @unserialize($auth['extra_data']);
		}

		if (empty($auth['extra_data']['token']['access_token']))
		{
			// old version...
			return false;
		}

		if (empty($auth['extra_data']['token']['expire_date']))
		{
			// old version...
			return false;
		}

		if ($auth['extra_data']['token']['expire_date'] < time())
		{
			// expired
			// note: we are checking against time() here, not XenForo_Application::$time
			$externalToken = bdApiConsumer_Helper_Api::getAccessTokenFromRefreshToken($provider, $auth['extra_data']['token']['refresh_token']);
			if (empty($externalToken))
			{
				return false;
			}

			$this->bdApiConsumer_updateExternalAuthAssociation($provider, $auth['provider_key'], $auth['user_id'], array_merge($auth['extra_data'], array('token' => $externalToken)));
			$auth['extra_data']['token'] = $externalToken;
		}

		return $auth['extra_data']['token']['access_token'];
	}

	public function bdApiConsumer_updateExternalAuthAssociation(array $provider, $providerKey, $userId, array $extra)
	{
		$providerCode = $this->bdApiConsumer_getProviderCode($provider);

		if (!empty($extra['token']['expires_in']) AND empty($extra['token']['expire_date']))
		{
			// use time() instead of XenForo_Application::$time to avoid issues
			// when script is running for a long time in the background / CLI
			$extra['token']['expire_date'] = time() + $extra['token']['expires_in'];
		}

		if (bdApiConsumer_Option::get('takeOver', 'avatar'))
		{
			$avatarUrl = bdApiConsumer_Helper_Avatar::getAvatarUrlFromAuthExtra($extra);
			if (!empty($avatarUrl))
			{
				$this->getModelFromCache('XenForo_Model_Avatar')->bdApiConsumer_applyAvatar($userId, $avatarUrl);
			}
		}

		if (XenForo_Application::$versionId >= 1030000)
		{
			return $this->updateExternalAuthAssociation($providerCode, $providerKey, $userId, $extra);
		}
		else
		{
			return $this->updateExternalAuthAssociation($providerCode, $providerKey, $userId, $this->bdApiConsumer_getUserProfileField(), $extra);
		}
	}

	public function bdApiConsumer_deleteExternalAuthAssociation($provider, $providerKey, $userId)
	{
		if (XenForo_Application::$versionId >= 1030000)
		{
			return $this->deleteExternalAuthAssociation($provider, $providerKey, $userId);
		}
		else
		{
			return $this->deleteExternalAuthAssociation($provider, $providerKey, $userId, $this->bdApiConsumer_getUserProfileField());
		}
	}

	public function bdApiConsumer_getExternalAuthAssociations($userId)
	{
		$externalAuths = $this->fetchAllKeyed('
			SELECT *
			FROM `xf_user_external_auth`
			WHERE `user_id` = ?
				AND `provider` LIKE \'bdapi_%\'
		', 'provider', array($userId));

		foreach ($externalAuths as &$externalAuth)
		{
			$externalAuth['extra_data'] = @unserialize($externalAuth['extra_data']);
		}

		return $externalAuths;
	}

	public function bdApiConsumer_getExternalAuthAssociationsForProviderUser($provider, $providerKeys)
	{
		$providerCode = $this->bdApiConsumer_getProviderCode($provider);

		$externalAuths = $this->fetchAllKeyed("
			SELECT *
			FROM xf_user_external_auth
			WHERE provider = ?
				AND provider_key IN (" . $this->_getDb()->quote($providerKeys) . ")
			ORDER BY provider
		", 'provider_key', $providerCode);

		foreach ($externalAuths as &$externalAuth)
		{
			$externalAuth['extra_data'] = @unserialize($externalAuth['extra_data']);
		}

		return $externalAuths;
	}

}
