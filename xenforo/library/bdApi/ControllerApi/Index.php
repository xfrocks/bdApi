<?php

class bdApi_ControllerApi_Index extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$data = array(
			'links' => array(
				'nodes' 		=> bdApi_Link::buildApiLink('nodes'),
				'posts' 		=> bdApi_Link::buildApiLink('posts'),
				'threads' 	=> bdApi_Link::buildApiLink('threads'),
				'users' 		=> bdApi_Link::buildApiLink('users'),
				'oauth_authorize'
							=> bdApi_Link::buildApiLink('oauth/authorize', array(), array(OAUTH2_TOKEN_PARAM_NAME => '')),
			),
		);
		
		return $this->responseData('bdApi_ViewApi_Index', $data);
	}
	
	protected function _getScopeForAction($action)
	{
		// no scope checking for this controller
		return false;
	}
}