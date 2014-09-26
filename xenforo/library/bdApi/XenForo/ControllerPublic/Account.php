<?php

class bdApi_XenForo_ControllerPublic_Account extends XFCP_bdApi_XenForo_ControllerPublic_Account
{
	public function actionApi()
	{
		$visitor = XenForo_Visitor::getInstance();

		/* @var $clientModel bdApi_Model_Client */
		$clientModel = $this->getModelFromCache('bdApi_Model_Client');
		/* @var $clientModel bdApi_Model_Token */
		$tokenModel = $this->getModelFromCache('bdApi_Model_Token');

		$clients = $clientModel->getClients(array('user_id' => XenForo_Visitor::getUserId()), array());
		$tokens = $tokenModel->getTokens(array('user_id' => XenForo_Visitor::getUserId()), array('join' => bdApi_Model_Token::FETCH_CLIENT));

		$viewParams = array(
			'clients' => $clients,
			'tokens' => $tokens,

			'permClientNew' => $visitor->hasPermission('general', 'bdApi_clientNew'),
		);

		return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Api_Index', 'bdapi_account_api', $viewParams));
	}

	public function actionApiClientAdd()
	{
		$visitor = XenForo_Visitor::getInstance();
		if (!$visitor->hasPermission('general', 'bdApi_clientNew'))
		{
			return $this->responseNoPermission();
		}

		/* @var $clientModel bdApi_Model_Client */
		$clientModel = $this->getModelFromCache('bdApi_Model_Client');

		if ($this->_request->isPost())
		{
			$dwInput = $this->_input->filter(array(
				'name' => XenForo_Input::STRING,
				'description' => XenForo_Input::STRING,
				'redirect_uri' => XenForo_Input::STRING,
			));

			$dw = XenForo_DataWriter::create('bdApi_DataWriter_Client');
			$dw->bulkSet($dwInput);

			$dw->set('client_id', $clientModel->generateClientId());
			$dw->set('client_secret', $clientModel->generateClientSecret());
			$dw->set('user_id', $visitor->get('user_id'));

			$dw->save();

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CREATED, XenForo_Link::buildPublicLink('account/api'));
		}
		else
		{
			$viewParams = array();

			return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Api_Client_Add', 'bdapi_account_api_client_add', $viewParams));
		}
	}

	public function actionApiClientDelete()
	{
		$visitor = XenForo_Visitor::getInstance();
		/* @var $clientModel bdApi_Model_Client */
		$clientModel = $this->getModelFromCache('bdApi_Model_Client');

		$clientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
		$client = $clientModel->getClientByid($clientId);
		if (empty($client))
		{
			return $this->responseNoPermission();
		}
		if ($client['user_id'] != $visitor->get('user_id'))
		{
			return $this->responseNoPermission();
		}

		if ($this->_request->isPost())
		{
			$dw = XenForo_DataWriter::create('bdApi_DataWriter_Client');
			$dw->setExistingData($client, true);
			$dw->delete();

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED, XenForo_Link::buildPublicLink('account/api'));
		}
		else
		{
			$viewParams = array('client' => $client);

			return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Api_Client_Delete', 'bdapi_account_api_client_delete', $viewParams));
		}
	}

	public function actionApiTokenRevoke()
	{
		$visitor = XenForo_Visitor::getInstance();
		/* @var $clientModel bdApi_Model_AuthCode */
		$authCodeModel = $this->getModelFromCache('bdApi_Model_AuthCode');
		/* @var $clientModel bdApi_Model_Client */
		$clientModel = $this->getModelFromCache('bdApi_Model_Client');
		/* @var $clientModel bdApi_Model_RefreshToken */
		$refreshTokenModel = $this->getModelFromCache('bdApi_Model_RefreshToken');
		/* @var $clientModel bdApi_Model_Token */
		$tokenModel = $this->getModelFromCache('bdApi_Model_Token');

		$tokenId = $this->_input->filterSingle('token_id', XenForo_Input::STRING);
		$token = $tokenModel->getTokenByid($tokenId, array('join' => bdApi_Model_Token::FETCH_CLIENT));
		if (empty($token))
		{
			return $this->responseNoPermission();
		}
		if ($token['user_id'] != $visitor->get('user_id'))
		{
			return $this->responseNoPermission();
		}

		if ($this->_request->isPost())
		{
			// besides deleting all the tokens, we will delete all associated auth
			// code/refresh token too
			XenForo_Db::beginTransaction();

			try
			{
				$authCodes = $authCodeModel->getAuthCodes(array(
					'client_id' => $token['client_id'],
					'user_id' => $visitor->get('user_id'),
				));
				foreach ($authCodes as $authCode)
				{
					$authCodeDw = XenForo_DataWriter::create('bdApi_DataWriter_AuthCode');
					$authCodeDw->setExistingData($authCode, true);
					$authCodeDw->delete();
				}

				$tokens = $tokenModel->getTokens(array(
					'client_id' => $token['client_id'],
					'user_id' => $visitor->get('user_id'),
				));
				foreach ($tokens as $_token)
				{
					$tokenDw = XenForo_DataWriter::create('bdApi_DataWriter_Token');
					$tokenDw->setExistingData($_token, true);
					$tokenDw->delete();
				}

				$refreshTokens = $refreshTokenModel->getRefreshTokens(array(
					'client_id' => $token['client_id'],
					'user_id' => $visitor->get('user_id'),
				));
				foreach ($refreshTokens as $refreshToken)
				{
					$refreshTokenDw = XenForo_DataWriter::create('bdApi_DataWriter_RefreshToken');
					$refreshTokenDw->setExistingData($refreshToken, true);
					$refreshTokenDw->delete();
				}

				XenForo_Db::commit();
			}
			catch (Exception $e)
			{
				XenForo_Db::rollback();
				throw $e;
			}

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED, XenForo_Link::buildPublicLink('account/api'));
		}
		else
		{
			$viewParams = array('token' => $token);

			return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Api_Token_Revoke', 'bdapi_account_api_token_revoke', $viewParams));
		}
	}

	public function actionApiData()
	{
		$callback = $this->_input->filterSingle('callback', XenForo_Input::STRING);
		$cmd = $this->_input->filterSingle('cmd', XenForo_Input::STRING);
		$clientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
		$data = array();

		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

		/* @var $tokenModel bdApi_Model_Token */
		$tokenModel = $oauth2Model->getTokenModel();

		/* @var $clientModel bdApi_Model_Client */
		$clientModel = $oauth2Model->getClientModel();

		$client = $clientModel->getClientById($clientId);
		$visitor = XenForo_Visitor::getInstance();

		if (!empty($client) AND $visitor['user_id'] > 0)
		{
			switch ($cmd)
			{
				case 'authorized':
					$scope = $this->_input->filterSingle('scope', XenForo_Input::STRING);
					$data[$cmd] = 0;

					if ($data[$cmd] === 0 AND $clientModel->canAutoAuthorize($client, $scope))
					{
						// this client has auto authorize setting for the requested scope
						// response with authorized = 1
						// note: we don't have (and don't need) an access token for now
						// but in case the client application request authorization, it
						// will be granted automatically anyway
						$data[$cmd] = 1;
					}

					if ($data[$cmd] === 0)
					{
						// start looking for accepted scopes
						$requestedScopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($scope);
						if (!empty($requestedScopes))
						{
							$userScopes = $this->getModelFromCache('bdApi_Model_UserScope')->getUserScopes($client['client_id'], $visitor['user_id']);
							$requestedScopesAccepted = array();
							foreach ($requestedScopes as $scope)
							{
								foreach ($userScopes as $userScope)
								{
									if ($userScope['scope'] === $scope)
									{
										$requestedScopesAccepted[] = $scope;
									}
								}
							}

							if (count($requestedScopes) === count($requestedScopesAccepted))
							{
								$data[$cmd] = 1;
							}
						}
					}

					if ($data[$cmd] === 1)
					{
						$data['user_id'] = $visitor['user_id'];
					}

					// switch ($cmd)
					break;
			}

			$clientModel->signApiData($client, $data);
		}

		$viewParams = array(
			'callback' => $callback,
			'cmd' => $cmd,
			'client_id' => $clientId,
			'data' => $data,
		);

		return $this->responseView('bdApi_ViewPublic_Account_Api_Data', '', $viewParams);
	}

	public function actionAuthorize()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

		/* @var $tokenModel bdApi_Model_Token */
		$tokenModel = $oauth2Model->getTokenModel();

		/* @var $clientModel bdApi_Model_Client */
		$clientModel = $oauth2Model->getClientModel();

		$authorizeParams = $this->_input->filter($oauth2Model->getAuthorizeParamsInputFilter());

		if ($this->_request->isPost())
		{
			// allow user to deny some certain scopes
			// only when this is a POST request, this should keep us safe from some vectors
			// of attack
			$scopesIncluded = $this->_input->filterSingle('scopes_included', XenForo_Input::UINT);
			$scopes = $this->_input->filterSingle('scopes', XenForo_Input::ARRAY_SIMPLE);
			if (!empty($scopesIncluded))
			{
				$authorizeParams['scope'] = bdApi_Template_Helper_Core::getInstance()->scopeJoin($scopes);
			}
		}

		$client = $clientModel->getClientById($authorizeParams['client_id']);
		if (empty($client))
		{
			throw new XenForo_Exception(new XenForo_Phrase('bdapi_authorize_error_client_x_not_found', array('client' => $authorizeParams['client_id'])));
		}

		// sondh@2013-03-19
		// this is a non-standard implementation: bypass confirmation dialog if the
		// client has appropriate option set
		$bypassConfirmation = false;
		if ($clientModel->canAutoAuthorize($client, $authorizeParams['scope']))
		{
			$bypassConfirmation = true;
		}

		// sondh@2014-09-26
		// bypass confirmation if all requested scopes have been granted at some point
		// in old version of this add-on, it checked for scope from active tokens
		// from now on, we look for all scopes (no expiration) for better user experience
		// if a token expires, it should not invalidate all user's choices
		$userScopes = $this->getModelFromCache('bdApi_Model_UserScope')->getUserScopes($client['client_id'], XenForo_Visitor::getUserId());
		$paramScopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($authorizeParams['scope']);
		$paramScopesNew = array();
		foreach ($paramScopes as $paramScope)
		{
			if (!isset($userScopes[$paramScope]))
			{
				$paramScopesNew[] = $paramScope;
			}
		}
		if (empty($paramScopesNew))
		{
			$bypassConfirmation = true;
		}
		else
		{
			$authorizeParams['scope'] = bdApi_Template_Helper_Core::getInstance()->scopeJoin($paramScopesNew);
		}

		// use the server get authorize params method to perform some extra validation
		$serverAuthorizeParams = $oauth2Model->getServer()->getAuthorizeParams();
		$authorizeParams = array_merge($serverAuthorizeParams, $authorizeParams);

		if ($this->_request->isPost() OR $bypassConfirmation)
		{
			$accept = $this->_input->filterSingle('accept', XenForo_Input::STRING);
			$accepted = !!$accept;

			if ($bypassConfirmation)
			{
				// sondh@2013-03-19
				// of course if the dialog was bypassed, $accepted should be true
				$accepted = true;
			}

			if ($accepted)
			{
				// sondh@2014-09-26
				// get all up to date user scopes and include in the new token
				// that means client only need to ask for a scope once and they will always have
				// that scope in future authorizations, even if they ask for less scope!
				// making it easy for client dev, they don't need to track whether they requested
				// a scope before. Just check the most recent token for that information.
				$paramScopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($authorizeParams['scope']);
				foreach ($userScopes as $userScope => $userScopeInfo)
				{
					if (!in_array($userScope, $paramScopes, true))
					{
						$paramScopes[] = $userScope;
					}
				}
				$paramScopes = array_unique($paramScopes);
				asort($paramScopes);
				$authorizeParams['scope'] = bdApi_Template_Helper_Core::getInstance()->scopeJoin($paramScopes);
			}

			$oauth2Model->getServer()->finishClientAuthorization($accepted, $authorizeParams);

			// finishClientAuthorization will redirect the page for us...
			exit ;
		}
		else
		{
			$viewParams = array(
				'client' => $client,
				'authorizeParams' => $authorizeParams,
			);

			return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Authorize', 'bdapi_account_authorize', $viewParams));
		}
	}

	protected function _preDispatch($action)
	{
		try
		{
			return parent::_preDispatch($action);
		}
		catch (XenForo_ControllerResponse_Exception $e)
		{
			if ($action === 'Authorize')
			{
				// this is our action and an exception is thrown
				// check to see if it is a registrationRequired error
				$controllerResponse = $e->getControllerResponse();
				if ($controllerResponse instanceof XenForo_ControllerResponse_Reroute AND $controllerResponse->controllerName == 'XenForo_ControllerPublic_Error' AND $controllerResponse->action == 'registrationRequired')
				{
					// so it is...
					$requestPaths = XenForo_Application::get('requestPaths');
					$session = XenForo_Application::getSession();
					$session->set('bdApi_authorizePending', $requestPaths['fullUri']);

					$controllerResponse->action = 'authorizeGuest';
				}
			}

			throw $e;
		}
	}

	protected function _checkCsrf($action)
	{
		if ($action === 'ApiData')
		{
			return;
		}

		return parent::_checkCsrf($action);
	}

}
