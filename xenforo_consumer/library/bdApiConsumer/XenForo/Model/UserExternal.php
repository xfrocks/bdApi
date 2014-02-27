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

	public function bdApiConsumer_updateExternalAuthAssociation($provider, $providerKey, $userId, array $extra)
	{
		if (XenForo_Application::$versionId >= 1030000)
		{
			return $this->updateExternalAuthAssociation($provider, $providerKey, $userId, $extra);
		}
		else
		{
			return $this->updateExternalAuthAssociation($provider, $providerKey, $userId, $this->bdApiConsumer_getUserProfileField(), $extra);
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

}
