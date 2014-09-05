<?php

class bdApi_ControllerApi_Tool extends bdApi_ControllerApi_Abstract
{
	public function actionGetLogin()
	{
		$input = $this->_input->filter(array(
			'oauth_token' => XenForo_Input::STRING,
			'redirect_uri' => XenForo_Input::STRING,
		));

		if (empty($input['redirect_uri']))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_tools_login_requires_redirect_uri'), 400);
		}
		if (!XenForo_Application::getSession()->isValidRedirectUri($input['redirect_uri']))
		{
			return $this->responseNoPermission();
		}

		$loginLinkData = array(
			'redirect' => $input['redirect_uri'],
			'timestamp' => XenForo_Application::$time + 10,
		);

		$loginLinkData['user_id'] = bdApi_Crypt::encryptTypeOne(XenForo_Visitor::getUserId(), $loginLinkData['timestamp']);

		$loginLink = XenForo_Link::buildPublicLink('login/api', '', $loginLinkData);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, $loginLink);
	}

	public function actionPostLoginSocial()
	{
		$social = array();

		$options = XenForo_Application::getOptions();

		if ($options->get('facebookAppId'))
		{
			$social[] = 'facebook';
		}

		if ($options->get('twitterAppKey'))
		{
			$social[] = 'twitter';
		}

		if ($options->get('googleClientId'))
		{
			$social[] = 'google';
		}

		return $this->responseData('bdApi_ViewApi_Tool_LoginSocial', array('social' => $social));
	}

	public function actionGetLogout()
	{
		$input = $this->_input->filter(array(
			'oauth_token' => XenForo_Input::STRING,
			'redirect_uri' => XenForo_Input::STRING,
		));

		if (empty($input['redirect_uri']))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_tools_login_requires_redirect_uri'), 400);
		}
		if (!XenForo_Application::getSession()->isValidRedirectUri($input['redirect_uri']))
		{
			return $this->responseNoPermission();
		}

		$logoutLinkData = array(
			'redirect' => $input['redirect_uri'],
			'_xfToken' => XenForo_Visitor::getInstance()->get('csrf_token_page'),
			'timestamp' => XenForo_Application::$time + 10,
		);

		$logoutLinkData['md5'] = bdApi_Crypt::encryptTypeOne(md5($logoutLinkData['redirect']), $logoutLinkData['timestamp']);

		$logoutLink = XenForo_Link::buildPublicLink('logout', '', $logoutLinkData);

		return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT, $logoutLink);
	}

	public function actionPostPasswordResetRequest()
	{
		$this->_assertRegistrationRequired();

		$user = XenForo_Visitor::getInstance()->toArray();

		$this->getModelFromCache('XenForo_Model_UserConfirmation')->sendPasswordResetRequest($user);

		return $this->responseMessage(new XenForo_Phrase('password_reset_request_has_been_emailed_to_you'));
	}

	public function actionPostLink()
	{
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING, array('default' => 'public'));
		$route = $this->_input->filterSingle('route', XenForo_Input::STRING, array('default' => 'index'));

		switch ($type)
		{
			case 'admin':
				$link = XenForo_Link::buildAdminLink($route);
				break;
			case 'public':
			default:
				$link = XenForo_Link::buildPublicLink($route);
				break;
		}

		$data = array(
			'type' => $type,
			'route' => $route,
			'link' => $link,
		);

		return $this->responseData('bdApi_ViewApi_Tool_Link', $data);
	}

	protected function _getScopeForAction($action)
	{
		return false;
	}

	/**
	 *
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}

}
