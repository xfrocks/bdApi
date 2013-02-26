<?php
class bdApiConsumer_XenForo_Model_UserExternal extends XFCP_bdApiConsumer_XenForo_Model_UserExternal
{
	public function bdApiConsumer_getProviderCode(array $producer)
	{
		return 'bdapi_' . $producer['code'];
	}
	
	public function bdApiConsumer_getUserProfileField()
	{
		return 'bdapiconsumer_unused';
	}
}