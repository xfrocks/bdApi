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
}