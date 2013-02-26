<?php

class bdApi_XenForo_ControllerPublic_Account extends XFCP_bdApi_XenForo_ControllerPublic_Account
{
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