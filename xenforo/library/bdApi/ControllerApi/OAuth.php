<?php

class bdApi_ControllerApi_OAuth extends bdApi_ControllerApi_Abstract
{
	public function actionGetAuthorize()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

		$authorizeParams = $oauth2Model->getServer()->getAuthorizeParams();
		$authorizeParams['social'] = $this->_input->filterSingle('social', XenForo_Input::STRING);

		$targetLink = XenForo_Link::buildPublicLink('account/authorize', array(), $authorizeParams);

		header('Location: ' . $targetLink);
		exit ;
	}

	public function actionGetToken()
	{
		return $this->responseError(new XenForo_Phrase('bdapi_slash_oauth_token_only_accepts_post_requests'), 404);
	}

	public function actionPostToken()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

		$oauth2Model->getServer()->grantAccessToken();

		// grantAccessToken will send output for us...
		exit ;
	}

	protected function _getScopeForAction($action)
	{
		// no scope checking for this controller
		return false;
	}

}
