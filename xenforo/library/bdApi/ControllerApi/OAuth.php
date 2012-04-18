<?php

class bdApi_ControllerApi_OAuth extends bdApi_ControllerApi_Abstract
{
	public function actionGetAuthorize()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');
		
		$authorizeParams = $this->_input->filter($oauth2Model->getAuthorizeParamsInputFilter());
		
		$targetLink = bdApi_Link::buildPublicLink('account/authorize', array(), $authorizeParams);
		
		header('Location: ' . $targetLink);
		exit;
	}
	
	public function actionPostToken()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');
		
		$oauth2Model->getServer()->grantAccessToken();
		
		// grantAccessToken will send output for us...
		exit;
	}
	
	protected function _getScopeForAction($action)
	{
		// no scope checking for this controller
		return false;
	}
}