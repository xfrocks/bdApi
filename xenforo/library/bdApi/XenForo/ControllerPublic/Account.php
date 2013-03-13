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
		
		$clients = $clientModel->getClients(
			array(
				'user_id' => XenForo_Visitor::getUserId(),
			),
			array(
			)
		);
		$tokens = $tokenModel->getTokens(
			array(
				'user_id' => XenForo_Visitor::getUserId(),
			),
			array(
				'join' => bdApi_Model_Token::FETCH_CLIENT,
			)
		);
		
		$viewParams = array(
			'clients' => $clients,
			'tokens' => $tokens,
		
			'permClientNew' => $visitor->hasPermission('general', 'bdApi_clientNew'),
		);
		
		return $this->_getWrapper(
			'account', 'bdApi',
			$this->responseView('bdApi_ViewPublic_Account_Api_Index', 'bdapi_account_api', $viewParams)
		);
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
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CREATED,
				XenForo_Link::buildPublicLink('account/api')
			);
		}
		else 
		{
			$viewParams = array(
			);
			
			return $this->_getWrapper(
				'account', 'bdApi',
				$this->responseView('bdApi_ViewPublic_Account_Api_Client_Add', 'bdapi_account_api_client_add', $viewParams)
			);
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
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildPublicLink('account/api')
			);
		}
		else 
		{
			$viewParams = array(
				'client' => $client,
			);
			
			return $this->_getWrapper(
				'account', 'bdApi',
				$this->responseView('bdApi_ViewPublic_Account_Api_Client_Delete', 'bdapi_account_api_client_delete', $viewParams)
			);
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
		$token = $tokenModel->getTokenByid($tokenId, array(
			'join' => bdApi_Model_Token::FETCH_CLIENT,
		));
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
			// besides deleting all the tokens, we will delete all associated auth code/refresh token too
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
			
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
				XenForo_Link::buildPublicLink('account/api')
			);
		}
		else 
		{
			$viewParams = array(
				'token' => $token,
			);
			
			return $this->_getWrapper(
				'account', 'bdApi',
				$this->responseView('bdApi_ViewPublic_Account_Api_Token_Revoke', 'bdapi_account_api_token_revoke', $viewParams)
			);
		}
	}
	
	public function actionAuthorize()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');
		
		/* @var $tokenModel bdApi_Model_Token */
		$tokenModel = $this->getModelFromCache('bdApi_Model_Token');

		$authorizeParams = $this->_input->filter($oauth2Model->getAuthorizeParamsInputFilter());
		
		// allow user to deny some certain scopes
		$scopesIncluded = $this->_input->filterSingle('scopes_included', XenForo_Input::UINT);
		$scopes = $this->_input->filterSingle('scopes', XenForo_Input::ARRAY_SIMPLE);
		if (!empty($scopesIncluded))
		{
			$authorizeParams['scope'] = implode(',', $scopes);
		}
		
		if ($this->_request->isPost())
		{
			$accept = $this->_input->filterSingle('accept', XenForo_Input::STRING);
			$accepted = !!$accept;
			
			$oauth2Model->getServer()->finishClientAuthorization($accepted, $authorizeParams);
			
			// finishClientAuthorization will redirect the page for us...
			exit;
		}
		else 
		{
			$client = $oauth2Model->getClientModel()->getClientById($authorizeParams['client_id']);
			
			if (empty($client))
			{
				throw new XenForo_Exception(new XenForo_Phrase('bdapi_authorize_error_client_x_not_found', array('client' => $authorizeParams['client_id'])));
			}
			
			// sondh@2013-02-17
			// try to get a working access token if the response_type == OAUTH2_AUTH_RESPONSE_TYPE_AUTH_CODE
			$oauth2Model->getServer(); // load the constants
			if ($authorizeParams['response_type'] == OAUTH2_AUTH_RESPONSE_TYPE_AUTH_CODE)
			{
				$activeTokens = $tokenModel->getTokens(array(
					'client_id' => $client['client_id'],
					'user_id' => XenForo_Visitor::getUserId(),
				));

				foreach ($activeTokens as $activeToken)
				{
					if ($activeToken['expire_date'] > 0 AND $activeToken['expire_date'] < XenForo_Application::$time)
					{
						// hmm, this token has expired
						// it should be cleaned up by the cron eh? Hopefully soon...
						continue;
					}

					$scopeArray = explode(',', $authorizeParams['scope']);
					$activeTokenScopes = explode(',', $activeToken['scope']);
					foreach ($scopeArray as $scopeSingle)
					{
						// use simple in_array check here without any normalization
						// in worst case scenario, user will just need to authorize again
						if (!in_array($scopeSingle, $activeTokenScopes))
						{
							// this token doesn't have enough scopes
							continue;
						}
					}

					// reached here? This is a good token, return it asap
					$oauth2Model->getServer()->finishClientAuthorizationWithAccessToken($authorizeParams, $activeToken['token_text']);
				}
			}

			$viewParams = array(
				'client' => $client,
				'authorizeParams' => $authorizeParams,
			);
			
			return $this->_getWrapper(
				'account', 'bdApi',
				$this->responseView('bdApi_ViewPublic_Account_Authorize', 'bdapi_account_authorize', $viewParams)
			);
		}
	}
} 