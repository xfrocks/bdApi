<?php
class bdApi_Template_Helper_Core
{
	public function scopeSplit($scopesStr)
	{
		return array_map('trim', explode(' ', $scopesStr));
	}
	
	public function scopeJoin(array $scopes)
	{
		return implode(' ', array_map('trim', $scopes));
	}
	
	public function scopeGetText($scope)
	{
		switch ($scope)
		{
			case bdApi_Model_OAuth2::SCOPE_READ: return new XenForo_Phrase('bdapi_scope_read');
			case bdApi_Model_OAuth2::SCOPE_POST: return new XenForo_Phrase('bdapi_scope_post');
			case bdApi_Model_OAuth2::SCOPE_MANAGE_ACCOUNT_SETTINGS: return new XenForo_Phrase('bdapi_scope_manage_account_settings');
			case bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS: return new XenForo_Phrase('bdapi_scope_participate_in_conversations');
			case bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM: return new XenForo_Phrase('bdapi_scope_manage_system');
		}
		
		return false;
	}
	
	private function __construct()
	{
		// singleton
	}
	
	private function __clone()
	{
		// singleton
	}
	
	/**
	 * Singleton instance
	 * @var bdApi_Template_Helper_Core
	 */
	private static $_instance = null;
	
	/**
	 * @return bdApi_Template_Helper_Core
	 */
	public static function getInstance()
	{
		if (self::$_instance === null)
		{
			$templateHelperClass = XenForo_Application::resolveDynamicClass(__CLASS__, 'bdapi_template_helper');
			self::$_instance = new $templateHelperClass();
		}
		
		return self::$_instance;
	}
}