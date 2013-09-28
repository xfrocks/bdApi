<?php

class bdApi_ControllerApi_Index extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$data = array('links' => array(
				'categories' => bdApi_Link::buildApiLink('categories'),
				'conversations' => Appforo_Link::buildAppforoLink('conversations'),
				'conversation-messages' => Appforo_Link::buildAppforoLink('conversation-messages'),

				'forums' => bdApi_Link::buildApiLink('forums'),
				'posts' => bdApi_Link::buildApiLink('posts'),
				'search' => Appforo_Link::buildAppforoLink('search'),
				'threads' => bdApi_Link::buildApiLink('threads'),
				'threads/recent' => bdApi_Link::buildApiLink('threads/recent'),
				'threads/new' => bdApi_Link::buildApiLink('threads/new'),
				'users' => bdApi_Link::buildApiLink('users'),

				'oauth_authorize' => bdApi_Link::buildApiLink('oauth/authorize', array(), array(OAUTH2_TOKEN_PARAM_NAME => '')),
				'oauth_token' => bdApi_Link::buildApiLink('oauth/token', array(), array(OAUTH2_TOKEN_PARAM_NAME => '')),
			));

		return $this->responseData('bdApi_ViewApi_Index', $data);
	}

	protected function _getScopeForAction($action)
	{
		// no scope checking for this controller
		return false;
	}

}
