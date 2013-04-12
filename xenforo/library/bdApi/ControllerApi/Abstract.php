<?php

abstract class bdApi_ControllerApi_Abstract extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Builds are response with specified data. Basically it's the same
	 * XenForo_ControllerPublic_Abstract::responseView() but with the
	 * template name removed so only view name and data array is available.
	 * Also, the data has some rules enforced to make a good response. 
	 * 
	 * @param string $viewName
	 * @param array $data
	 */
	public function responseData($viewName, array $data = array())
	{
		return parent::responseView($viewName, 'DEFAULT', $data);
	}
	
	/**
	 * Gets the required scope for a controller action. By default,
	 * all API GET actions will require the read scope, POST actions will require
	 * the post scope.
	 * 
	 * Special case: if no OAuth token is specified (the session
	 * will be setup as guest), GET actions won't require the read scope anymore.
	 * That means guest-permission API requests will have the read scope 
	 * automatically.
	 * 
	 * @param string $action
	 * 
	 * @return string required scope. One of the SCOPE_* constant in bdApi_Model_OAuth2
	 */
	protected function _getScopeForAction($action)
	{
		if (strpos($action, 'Post') === 0)
		{
			return bdApi_Model_OAuth2::SCOPE_POST;
		}
		elseif (strpos($action, 'Put') === 0)
		{
			// TODO: separate scope?
			return bdApi_Model_OAuth2::SCOPE_POST;
		}
		elseif (strpos($action, 'Delete') === 0)
		{
			// TODO: separate scope?
			return bdApi_Model_OAuth2::SCOPE_POST;
		}
		else 
		{
			if (XenForo_Visitor::getUserId() > 0)
			{
				return bdApi_Model_OAuth2::SCOPE_READ;
			}
			else
			{
				return false;
			}
		}
	}
	
	/**
	 * Helper to check for the required scope and throw an exception
	 * if it could not be found.
	 */
	protected function _assertRequiredScope($scope)
	{
		if (empty($scope))
		{
			// no scope is required
			return;
		}
		
		/* @var $session bdApi_Session */
		$session = XenForo_Application::get('session');
		
		$oauthTokenText = $session->getOAuthTokenText();
		if (empty($oauthTokenText))
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('bdapi_authorize_error_invalid_or_expired_access_token'), 403)
			);
		}
		
		if (!$session->checkScope($scope))
		{
			throw $this->responseException(
				$this->responseError(new XenForo_Phrase('bdapi_authorize_error_scope_x_not_granted', array('scope' => $scope)), 403)
			);
		}
	}
	
	public function responseView($viewName, $templateName = 'DEFAULT', array $params = array(), array $containerParams = array())
	{
		throw new XenForo_Exception('bdApi_ControllerApi_Abstract::responseView() is not available.');
	}
	
	public function responseRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
	{
		$data = array(
			'redirect' => array(
				'type' => $redirectType,
				'target' => $redirectTarget,
			),
		);
		
		if ($redirectMessage !== null)
		{
			$data['redirect']['message'] = $redirectMessage;
		}
		
		return $this->responseData('', $data);
	}
	
	public function responseNoPermission()
	{
		return $this->responseReroute('bdApi_ControllerApi_Error', 'noPermission');
	}
	
	public function updateSessionActivity($controllerResponse, $controllerName, $action)
	{
		// disable session activity for api requests
		return;
	}
	
	protected function _preDispatch($action)
	{
		$requiredScope = $this->_getScopeForAction($action);
		$this->_assertRequiredScope($requiredScope);
		
		parent::_preDispatch($action);
	}
	
	protected function _setupSession($action)
	{
		if (XenForo_Application::isRegistered('session'))
		{
			return;
		}

		$session = bdApi_Session::startApiSession($this->_request);
	}
	
	protected function _checkCsrf($action)
	{
		// do not check csrf for api requests
		self::$_executed['csrf'] = true;
		return;
	}
}