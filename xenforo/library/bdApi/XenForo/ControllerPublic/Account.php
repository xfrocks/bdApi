<?php

class bdApi_XenForo_ControllerPublic_Account extends XFCP_bdApi_XenForo_ControllerPublic_Account
{
	public function actionAuthorize()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');
		
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