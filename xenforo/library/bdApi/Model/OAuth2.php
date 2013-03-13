<?php

class bdApi_Model_OAuth2 extends XenForo_Model
{
	const SCOPE_READ = 'read';
	const SCOPE_POST = 'post';
	const SCOPE_MANAGE_ACCOUNT_SETTINGS = 'usercp';
	const SCOPE_PARTICIPATE_IN_CONVERSATIONS = 'conversate';
	const SCOPE_MANAGE_SYSTEM = 'admincp';
	
	protected static $_serverInstance = false;
	
	/**
	 * Gets the server object. Only one instance will be created for
	 * each page request.
	 * 
	 * @return bdApi_OAuth2
	 */
	public function getServer()
	{
		if (self::$_serverInstance === false)
		{
			self::$_serverInstance = new bdApi_OAuth2($this);
		}
		
		return self::$_serverInstance;
	}
	
	public function getAuthorizeParamsInputFilter()
	{
		return array(
			'client_id' 			=> XenForo_Input::STRING,
			'response_type' 		=> XenForo_Input::STRING,
			'redirect_uri' 			=> XenForo_Input::STRING,
			'state' 				=> XenForo_Input::STRING,
			'scope' 				=> XenForo_Input::STRING,
		);
	}
	
	/**
	 * Gets supported scopes for server. Other add-ons can override 
	 * this method to support more scopes.
	 * 
	 * @return array an array of supported scopes
	 */
	public function getSystemSupportedScopes()
	{
		return array(
			self::SCOPE_READ,
			self::SCOPE_POST,
			self::SCOPE_MANAGE_ACCOUNT_SETTINGS,
			self::SCOPE_PARTICIPATE_IN_CONVERSATIONS,
			self::SCOPE_MANAGE_SYSTEM,
		);
	}
	
	/**
	 * Gets the authentication realm for server. This will be display
	 * in the authentication dialog (in browsers and such). By default,
	 * the XenForo's board title will be used but if it's not available
	 * or is empty, a generic name will be used ("XenForo")
	 * 
	 * @return string the realm
	 */
	public function getSystemAuthenticationRealm()
	{
		$options = XenForo_Application::get('options');
		$boardTitle = $options->get('boardTitle');
		
		if (empty($boardTitle))
		{
			// no board title, just use a generic name
			$boardTitle = 'XenForo';
		}
		
		$realm = new XenForo_Phrase('bdapi_realm', array('boardTitle' => $boardTitle));
		$realm .= ''; // convert to string
	}
	
	/**
	 * @return XenForo_Model_User
	 */
	public function getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
	
	/**
	 * @return bdApi_Model_AuthCode
	 */
	public function getAuthCodeModel()
	{
		return $this->getModelFromCache('bdApi_Model_AuthCode');
	}
	
	/**
	 * @return bdApi_Model_Client
	 */
	public function getClientModel()
	{
		return $this->getModelFromCache('bdApi_Model_Client');
	}
	
	/**
	 * @return bdApi_Model_RefreshToken
	 */
	public function getRefreshTokenModel()
	{
		return $this->getModelFromCache('bdApi_Model_RefreshToken');
	}
	
	/**
	 * @return bdApi_Model_Token
	 */
	public function getTokenModel()
	{
		return $this->getModelFromCache('bdApi_Model_Token');
	}
}