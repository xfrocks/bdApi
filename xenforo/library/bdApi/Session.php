<?php

class bdApi_Session extends XenForo_Session
{
	/**
	 * The effective OAuth token of current request.
	 *
	 * @var array|false
	 */
	protected $_oauthToken = false;

	/**
	 * The effective OAuth client of current request.
	 *
	 * @var array|false
	 */
	protected $_oauthClient = false;

	public function getOAuthClientId()
	{
		if (!empty($this->_oauthToken))
		{
			return $this->_oauthToken['client_id'];
		}

		return '';
	}

	public function getOAuthClientSecret()
	{
		if (!empty($this->_oauthToken))
		{
			if (empty($this->_oauthClient))
			{
				$this->_oauthClient = XenForo_Model::create('bdApi_Model_Client')->getClientById($this->_oauthToken['client_id']);
			}

			return $this->_oauthClient['client_secret'];
		}

		return false;
	}

	/**
	 * Gets the effective OAuth token text of current request or false
	 * if no token could be found.
	 */
	public function getOAuthTokenText()
	{
		if (!empty($this->_oauthToken))
		{
			return $this->_oauthToken['token_text'];
		}

		return false;
	}

	/**
	 * Checks for the specified scope to see if the effective scopes
	 * contain it.
	 *
	 * @param string $scope
	 *
	 * @return boolean true if the scope is found
	 */
	public function checkScope($scope)
	{
		if (empty($this->_oauthToken))
		{
			// no token, obviously no scope
			return false;
		}

		$scopes = $this->get('scopes');
		if (empty($scopes))
		{
			// no scopes...
			return false;
		}

		return in_array($scope, $scopes);
	}

	/**
	 * Starts running the API session handler. This will automatically log in the
	 * user via OAuth if needed, and setup the visitor object. The session will be
	 * registered in the registry.
	 *
	 * @param Zend_Controller_Request_Http|null $request
	 *
	 * @return XenForo_Session
	 */
	public static function startApiSession(Zend_Controller_Request_Http $request = null)
	{
		if (!$request)
		{
			$request = new Zend_Controller_Request_Http();
		}

		$session = new bdApi_Session();
		$session->start();

		XenForo_Application::set('session', $session);

		$options = $session->getAll();

		$visitor = XenForo_Visitor::setup($session->get('user_id'), $options);

		return $session;
	}

	public function start($sessionId = null, $ipAddress = null)
	{
		parent::start($sessionId, $ipAddress);

		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = XenForo_Model::create('bdApi_Model_OAuth2');

		$this->_oauthToken = $oauth2Model->getServer()->getEffectiveToken();

		if (!empty($this->_oauthToken) AND !empty($this->_oauthToken['user_id']))
		{
			$this->changeUserId($this->_oauthToken['user_id']);

			$scopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($this->_oauthToken['scope']);
			$this->set('scopes', $scopes);
		}
	}

	public function getSessionFromSource($sessionId)
	{
		// api sessions are not saved
		// so it's uncessary to query the db for it
		return false;
	}

	public function save()
	{
		// do nothing
	}

	public function saveSessionToSource($sessionId, $isUpdate)
	{
		// do nothing
	}

	public function delete()
	{
		// do nothing
	}

	public function deleteSessionFromSource($sessionId)
	{
		// do nothing
	}

}
