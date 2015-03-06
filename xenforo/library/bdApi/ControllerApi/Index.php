<?php

class bdApi_ControllerApi_Index extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();

        $systemInfo = array();
        if ($session->checkScope(bdApi_Model_OAuth2::SCOPE_POST)) {
            $systemInfo = array(
                // YYYYMMDD and 2 digits number (01-99), allowing maximum 99 revisions/day
                'api_revision' => 2014030701,
                'api_modules' => $this->_getModules(),
            );
        }

        $data = array(
            'links' => array(
                'conversations' => XenForo_Link::buildApiLink('conversations'),
                'conversation-messages' => XenForo_Link::buildApiLink('conversation-messages'),
                'notifications' => XenForo_Link::buildApiLink('notifications'),

                'search' => XenForo_Link::buildApiLink('search'),
                'navigation' => XenForo_Link::buildApiLink('navigation'),
                'threads' => XenForo_Link::buildApiLink('threads'),
                'threads/recent' => XenForo_Link::buildApiLink('threads/recent'),
                'threads/new' => XenForo_Link::buildApiLink('threads/new'),
                'posts' => XenForo_Link::buildApiLink('posts'),
                'users' => XenForo_Link::buildApiLink('users'),

                'batch' => XenForo_Link::buildApiLink('batch'),
                'subscriptions' => XenForo_Link::buildApiLink('subscriptions'),

                'oauth_authorize' => XenForo_Link::buildApiLink('oauth/authorize', array(), array(OAUTH2_TOKEN_PARAM_NAME => '')),
                'oauth_token' => XenForo_Link::buildApiLink('oauth/token', array(), array(OAUTH2_TOKEN_PARAM_NAME => '')),
            ),
            'system_info' => $systemInfo,
        );

        return $this->responseData('bdApi_ViewApi_Index', $data);
    }

    public function actionOptionsCors()
    {
        return $this->responseData('bdApi_ViewApi_Index_OptionsCors');
    }

    protected function _getModules()
    {
        return array(
            'forum' => 2015030601,
            'oauth2' => 2015030602,
            'subscription' => 2014092301,
        );
    }

    protected function _getScopeForAction($action)
    {
        // no scope checking for this controller
        return false;
    }

}
