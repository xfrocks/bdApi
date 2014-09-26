<?php

class bdApiConsumer_XenForo_ControllerPublic_Register extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Register
{
	const SESSION_KEY_REDIRECT = 'bdApiConsumer_redirect';

	public function actionExternal()
	{
		$providerCode = $this->_input->filterSingle('provider', XenForo_Input::STRING);
		$assocUserId = $this->_input->filterSingle('assoc', XenForo_Input::UINT);
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$provider = bdApiConsumer_Option::getProviderByCode($providerCode);
		if (empty($provider))
		{
			// this is one serious error
			throw new XenForo_Exception('Provider could not be determined');
		}

		$externalRedirectUri = XenForo_Link::buildPublicLink('canonical:register/external', false, array(
			'provider' => $providerCode,
			'assoc' => ($assocUserId ? $assocUserId : false),
		));

		if ($this->_input->filterSingle('reg', XenForo_Input::UINT))
		{
			$redirect = XenForo_Link::convertUriToAbsoluteUri($this->getDynamicRedirect());
			XenForo_Application::get('session')->set(self::SESSION_KEY_REDIRECT, $redirect);

			$social = $this->_input->filterSingle('social', XenForo_Input::STRING);
			$requestUrl = bdApiConsumer_Helper_Api::getRequestUrl($provider, $externalRedirectUri, array('social' => $social));

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL, $requestUrl);
		}

		// try to use the non-standard query parameter `t` first,
		// continue exchange code for access token later if that fails
		$externalCode = $this->_input->filterSingle('code', XenForo_Input::STRING);
		if (empty($externalCode))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array('provider' => $provider['name'])));
		}

		$externalToken = bdApiConsumer_Helper_Api::getAccessTokenFromCode($provider, $externalCode, $externalRedirectUri);
		if (empty($externalToken))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array('provider' => $provider['name'])));
		}

		$externalVisitor = bdApiConsumer_Helper_Api::getVisitor($provider, $externalToken['access_token']);
		if (empty($externalVisitor))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array('provider' => $provider['name'])));
		}
		if (empty($externalVisitor['user_email']))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_returned_unknown_error', array('provider' => $provider['name'])));
		}
		if (isset($externalVisitor['user_is_valid']) AND isset($externalVisitor['user_is_verified']))
		{
			if (empty($externalVisitor['user_is_valid']) OR empty($externalVisitor['user_is_verified']))
			{
				return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_account_not_good_standing', array('provider' => $provider['name'])));
			}
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$existingAssoc = $userExternalModel->getExternalAuthAssociation($userExternalModel->bdApiConsumer_getProviderCode($provider), $externalVisitor['user_id']);
		$autoRegistered = false;

		if (empty($existingAssoc))
		{
			$existingAssoc = $this->_bdApiConsumer_autoRegister($provider, $externalToken, $externalVisitor);
			if (!empty($existingAssoc))
			{
				$autoRegistered = true;
			}
		}

		if ($existingAssoc && $userModel->getUserById($existingAssoc['user_id']))
		{
			$redirect = XenForo_Application::get('session')->get(self::SESSION_KEY_REDIRECT);

			XenForo_Application::get('session')->changeUserId($existingAssoc['user_id']);
			XenForo_Visitor::setup($existingAssoc['user_id']);

			XenForo_Application::get('session')->remove(self::SESSION_KEY_REDIRECT);
			if (empty($redirect))
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			if (!$autoRegistered)
			{
				$userExternalModel->bdApiConsumer_updateExternalAuthAssociation($provider, $externalVisitor['user_id'], $existingAssoc['user_id'], $externalVisitor + array('token' => $externalToken));
			}

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
		}

		$existingUser = false;
		$emailMatch = false;
		if (XenForo_Visitor::getUserId())
		{
			$existingUser = XenForo_Visitor::getInstance();
		}
		elseif ($assocUserId)
		{
			$existingUser = $userModel->getUserById($assocUserId);
		}

		if (!$existingUser)
		{
			$existingUser = $userModel->getUserByEmail($externalVisitor['user_email']);
			$emailMatch = true;
		}

		if ($existingUser)
		{
			// must associate: matching user
			return $this->responseView('bdApiConsumer_ViewPublic_Register_External', 'bdapi_consumer_register', array(
				'associateOnly' => true,

				'provider' => $provider,
				'externalToken' => $externalToken,
				'externalVisitor' => $externalVisitor,

				'existingUser' => $existingUser,
				'emailMatch' => $emailMatch,
				'redirect' => $redirect
			));
		}

		if (bdApiConsumer_Option::get('bypassRegistrationActive'))
		{
			// do not check for registration active option
		}
		else
		{
			$this->_assertRegistrationActive();
		}

		$externalVisitor['username'] = bdApiConsumer_Helper_AutoRegister::suggestUserName($externalVisitor['username'], $userModel);

		return $this->responseView('bdApiConsumer_ViewPublic_Register_External', 'bdapi_consumer_register', array(
			'provider' => $provider,
			'externalToken' => $externalToken,
			'externalVisitor' => $externalVisitor,
			'redirect' => $redirect,

			'customFields' => $this->_getFieldModel()->prepareUserFields($this->_getFieldModel()->getUserFields(array('registration' => true)), true),

			'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		), $this->_getRegistrationContainerParams());
	}

	public function actionExternalRegister()
	{
		$this->_assertPostOnly();

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$providerCode = $this->_input->filterSingle('provider', XenForo_Input::STRING);
		$provider = bdApiConsumer_Option::getProviderByCode($providerCode);
		if (empty($provider))
		{
			return $this->responseNoPermission();
		}

		$doAssoc = ($this->_input->filterSingle('associate', XenForo_Input::STRING) || $this->_input->filterSingle('force_assoc', XenForo_Input::UINT));
		if ($doAssoc)
		{
			$associate = $this->_input->filter(array(
				'associate_login' => XenForo_Input::STRING,
				'associate_password' => XenForo_Input::STRING
			));

			$loginModel = $this->_getLoginModel();

			if ($loginModel->requireLoginCaptcha($associate['associate_login']))
			{
				return $this->responseError(new XenForo_Phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
			}

			$userId = $userModel->validateAuthentication($associate['associate_login'], $associate['associate_password'], $error);
			if (!$userId)
			{
				$loginModel->logLoginAttempt($associate['associate_login']);
				return $this->responseError($error);
			}
		}

		$refreshToken = $this->_input->filterSingle('refresh_token', XenForo_Input::STRING);
		$externalToken = bdApiConsumer_Helper_Api::getAccessTokenFromRefreshToken($provider, $refreshToken);
		if (empty($externalToken))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array('provider' => $provider['name'])));
		}

		$externalVisitor = bdApiConsumer_Helper_Api::getVisitor($provider, $externalToken['access_token']);
		if (empty($externalVisitor))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array('provider' => $provider['name'])));
		}
		if (empty($externalVisitor['user_email']))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_returned_unknown_error', array('provider' => $provider['name'])));
		}
		if (isset($externalVisitor['user_is_valid']) AND isset($externalVisitor['user_is_verified']))
		{
			if (empty($externalVisitor['user_is_valid']) OR empty($externalVisitor['user_is_verified']))
			{
				return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_account_not_good_standing', array('provider' => $provider['name'])));
			}
		}

		if ($doAssoc)
		{
			$userExternalModel->bdApiConsumer_updateExternalAuthAssociation($provider, $externalVisitor['user_id'], $userId, $externalVisitor + array('token' => $externalToken));

			$redirect = XenForo_Application::get('session')->get(self::SESSION_KEY_REDIRECT);
			XenForo_Application::get('session')->changeUserId($userId);
			XenForo_Visitor::setup($userId);

			XenForo_Application::get('session')->remove(self::SESSION_KEY_REDIRECT);
			if (!$redirect)
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
		}

		if (bdApiConsumer_Option::get('bypassRegistrationActive'))
		{
			// do not check for registration active option
		}
		else
		{
			$this->_assertRegistrationActive();
		}

		$data = $this->_input->filter(array(
			'username' => XenForo_Input::STRING,
			'timezone' => XenForo_Input::STRING,
		));

		// TODO: custom fields

		if (XenForo_Dependencies_Public::getTosUrl() && !$this->_input->filterSingle('agree', XenForo_Input::UINT))
		{
			return $this->responseError(new XenForo_Phrase('you_must_agree_to_terms_of_service'));
		}

		$user = bdApiConsumer_Helper_AutoRegister::createUser($data, $provider, $externalToken, $externalVisitor, $this->_getUserExternalModel());

		XenForo_Application::get('session')->changeUserId($user['user_id']);
		XenForo_Visitor::setup($user['user_id']);

		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$viewParams = array(
			'user' => $user,
			'redirect' => ($redirect ? XenForo_Link::convertUriToAbsoluteUri($redirect) : ''),
		);

		return $this->responseView('XenForo_ViewPublic_Register_Process', 'register_process', $viewParams, $this->_getRegistrationContainerParams());
	}

	protected function _bdApiConsumer_autoRegister($provider, $externalToken, array $externalVisitor)
	{
		$mode = bdApiConsumer_Option::get('autoRegister');

		if ($mode !== 'on' AND $mode !== 'id_sync')
		{
			// not in working mode
			return false;
		}

		$data = array();

		$sameName = $this->_getUserModel()->getUserByName($externalVisitor['username']);
		if (!empty($sameName))
		{
			// username conflict found, too bad
			return false;
		}
		$data['username'] = $externalVisitor['username'];

		if ($mode === 'id_sync')
		{
			// additionally look for user with same ID
			$sameId = $this->_getUserModel()->getUserById($externalVisitor['user_id']);
			if (!empty($sameId))
			{
				// ID conflict found...
				return false;
			}
			$data['user_id'] = $externalVisitor['user_id'];
		}

		$user = bdApiConsumer_Helper_AutoRegister::createUser($data, $provider, $externalToken, $externalVisitor, $this->_getUserExternalModel());

		if (empty($user))
		{
			// for some reason, the user could not be created
			return false;
		}

		return $this->_getUserExternalModel()->getExternalAuthAssociation($this->_getUserExternalModel()->bdApiConsumer_getProviderCode($provider), $externalVisitor['user_id']);
	}

}
