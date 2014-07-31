<?php

class bdApiConsumer_XenForo_ControllerPublic_Login extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Login
{
	protected $_bdApiConsumer_beforeLoginVisitorId = 0;

	public function bdApiConsumer_getBeforeLoginVisitorId()
	{
		return $this->_bdApiConsumer_beforeLoginVisitorId;
	}

	protected function _preDispatch($action)
	{
		$this->_bdApiConsumer_beforeLoginVisitorId = XenForo_Visitor::getUserId();

		return parent::_preDispatch($action);
	}

	public function actionExternal()
	{
		$this->_assertPostOnly();

		$providerCode = $this->_input->filterSingle('provider', XenForo_Input::STRING);
		$provider = bdApiConsumer_Option::getProviderByCode($providerCode);
		if (empty($provider))
		{
			return $this->responseNoPermission();
		}

		$externalUserId = $this->_input->filterSingle('external_user_id', XenForo_Input::UINT);
		if (empty($externalUserId))
		{
			return $this->responseNoPermission();
		}

		if (!bdApiConsumer_Helper_Api::verifyJsSdkSignature($provider, $_REQUEST))
		{
			return $this->responseNoPermission();
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');

		$existingAssoc = $userExternalModel->getExternalAuthAssociation($userExternalModel->bdApiConsumer_getProviderCode($provider), $externalUserId);

		if (!empty($existingAssoc))
		{
			$accessToken = $userExternalModel->bdApiConsumer_getAccessTokenFromAuth($provider, $existingAssoc);
			if (empty($accessToken))
			{
				// no access token in the auth, consider no auth at all
				$existingAssoc = null;
			}
		}

		if (empty($existingAssoc))
		{
			$autoRegister = bdApiConsumer_Option::get('autoRegister');

			if ($autoRegister === 'on' OR $autoRegister === 'id_sync')
			{
				// we have to do a refresh here
				return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildPublicLink('canonical:register/external', null, array(
					'provider' => $providerCode,
					'reg' => 1,
					'redirect' => $this->getDynamicRedirect(),
				)), new XenForo_Phrase('bdapi_consumer_being_auto_login_auto_register_x', array('provider' => $provider['name'])));
			}
		}

		if ($existingAssoc AND ($user = $userModel->getUserById($existingAssoc['user_id'])))
		{
			$userModel->setUserRememberCookie($user['user_id']);

			XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'login_api_consumer');

			$userModel->deleteSessionActivity(0, $this->_request->getClientIp(false));

			$session = XenForo_Application::get('session');

			$session->changeUserId($user['user_id']);
			XenForo_Visitor::setup($user['user_id']);

			$message = new XenForo_Phrase('bdapi_consumer_auto_login_with_x_succeeded_y', array(
				'provider' => $provider['name'],
				'username' => $user['username']
			));

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $this->getDynamicRedirect(), $message);
		}
		else
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_auto_login_with_x_failed', array('provider' => $provider['name'])));
		}
	}

}
