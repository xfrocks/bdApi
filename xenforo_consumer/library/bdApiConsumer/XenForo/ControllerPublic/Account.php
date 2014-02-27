<?php
class bdApiConsumer_XenForo_ControllerPublic_Account extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Account
{
	public function actionExternalAccounts()
	{
		$response = null;

		if (bdApiConsumer_Option::get('_is130+'))
		{
			$response = parent::actionExternalAccounts();

			if ($response instanceof XenForo_ControllerResponse_View OR empty($response->subView))
			{
				// good
			}
			else
			{
				// not a view? return it asap
				return $response;
			}
		}

		$visitor = XenForo_Visitor::getInstance();

		/* var $externalAuthModel XenForo_Model_UserExternal */
		$externalAuthModel = $this->getModelFromCache('XenForo_Model_UserExternal');

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		$externalAuths = $externalAuthModel->bdApiConsumer_getExternalAuthAssociations($visitor['user_id']);

		$providers = bdApiConsumer_Option::getProviders();

		$viewParams = array(
			'hasPassword' => $auth->hasPassword(),

			'bdApiConsumer_externalAuths' => $externalAuths,
			'bdApiConsumer_providers' => $providers,
		);

		if ($response == null)
		{
			$response = $this->_getWrapper('account', 'bdApiConsumer', $this->responseView('bdApiConsumer_ViewPublic_Account_External', 'bdapi_consumer_account_external', $viewParams));
		}
		else
		{
			$response->subView->params += $viewParams;
		}

		return $response;
	}

	public function actionExternalAccountsDisassociate()
	{
		if (bdApiConsumer_Option::get('_is130+'))
		{
			return parent::actionExternalAccountsDisassociate();
		}

		$this->_assertPostOnly();

		$visitor = XenForo_Visitor::getInstance();

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		/** @var XenForo_Model_UserExternal $externalAuthModel */
		$externalAuthModel = $this->getModelFromCache('XenForo_Model_UserExternal');

		$input = $this->_input->filter(array(
			'disassociate' => XenForo_Input::STRING,
			'account' => XenForo_Input::STRING
		));
		if ($input['disassociate'] && $input['account'])
		{
			$externalAuths = $externalAuthModel->bdApiConsumer_getExternalAuthAssociations($visitor['user_id']);

			foreach ($externalAuths as $externalAuth)
			{
				if ($externalAuth['provider'] === $input['account'])
				{
					$externalAuthModel->bdApiConsumer_deleteExternalAuthAssociation($externalAuth['provider'], $externalAuth['provider_key'], $visitor['user_id']);
				}
			}

			if (!$auth->hasPassword() && !$externalAuthModel->getExternalAuthAssociationsForUser($visitor['user_id']))
			{
				$this->getModelFromCache('XenForo_Model_UserConfirmation')->resetPassword($visitor['user_id']);
			}
		}

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('account/external-accounts'));
	}

	public function actionExternal()
	{
		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, XenForo_Link::buildPublicLink('account/external-accounts'));
	}

}
