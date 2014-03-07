<?php

class bdApi_ControllerApi_Index extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$systemInfo = array();

		if (XenForo_Visitor::getUserId() > 0)
		{
			$systemInfo = array(
				// YYYYMMDD and 2 digits number (01-99), allowing maximum 99 revisions/day
				'api_revision' => 2013092801,
				'api_modules' => $this->_getModules(),
			);
		}

		$data = array(
			'links' => array(
				'categories' => bdApi_Link::buildApiLink('categories'),
				'conversations' => bdApi_Link::buildApiLink('conversations'),
				'conversation-messages' => bdApi_Link::buildApiLink('conversation-messages'),
				'notifications' => bdApi_Link::buildApiLink('notifications'),

				'forums' => bdApi_Link::buildApiLink('forums'),
				'posts' => bdApi_Link::buildApiLink('posts'),
				'search' => bdApi_Link::buildApiLink('search'),
				'threads' => bdApi_Link::buildApiLink('threads'),
				'threads/recent' => bdApi_Link::buildApiLink('threads/recent'),
				'threads/new' => bdApi_Link::buildApiLink('threads/new'),
				'users' => bdApi_Link::buildApiLink('users'),

				'oauth_authorize' => bdApi_Link::buildApiLink('oauth/authorize', array(), array(OAUTH2_TOKEN_PARAM_NAME => '')),
				'oauth_token' => bdApi_Link::buildApiLink('oauth/token', array(), array(OAUTH2_TOKEN_PARAM_NAME => '')),
			),
			'system_info' => $systemInfo,
		);

		return $this->responseData('bdApi_ViewApi_Index', $data);
	}

	protected function _getModules()
	{
		return array(
			'forum' => 2014022602,
			'oauth2' => 2014030701,
		);
	}

	protected function _getScopeForAction($action)
	{
		// no scope checking for this controller
		return false;
	}

}
