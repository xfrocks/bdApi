<?php

class bdApi_ControllerApi_Index extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();

        $systemInfo = array();
        if ($session->getOAuthClientId() === '') {
            $systemInfo += array(
                'oauth/authorize' => XenForo_Link::buildApiLink('oauth/authorize', array(), array('oauth_token' => '')),
                'oauth/token' => XenForo_Link::buildApiLink('oauth/token', array(), array('oauth_token' => '')),
            );
        }
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
                'notifications' => XenForo_Link::buildApiLink('notifications'),

                'search' => XenForo_Link::buildApiLink('search'),
                'navigation' => XenForo_Link::buildApiLink('navigation', array(), array('parent' => 0)),
                'threads/recent' => XenForo_Link::buildApiLink('threads/recent'),
                'threads/new' => XenForo_Link::buildApiLink('threads/new'),
                'users' => XenForo_Link::buildApiLink('users'),
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
            'forum' => 2015081101,
            'oauth2' => 2015060501,
            'subscription' => 2014092301,
        );
    }

    protected function _getScopeForAction($action)
    {
        // no scope checking for this controller
        return false;
    }

    public static function getSessionActivityDetailsForList(array $activities)
    {
        $clientIds = array();
        foreach ($activities AS $activity) {
            if (!empty($activity['params']['client_id'])) {
                $clientIds[] = $activity['params']['client_id'];
            }
        }

        $clients = array();

        if (!empty($clientIds)) {
            $clientIds = array_unique($clientIds);

            /** @var bdApi_Model_Client $clientModel */
            $clientModel = XenForo_Model::create('bdApi_Model_Client');
            $clients = $clientModel->getClients(array('client_id' => $clientIds));
        }

        $output = array();
        foreach ($activities AS $key => $activity) {
            $client = null;
            if (!empty($activity['params']['client_id'])
                && isset($clients[$activity['params']['client_id']])
            ) {
                $client = $clients[$activity['params']['client_id']];
            }

            if (!empty($client)) {
                $output[$key] = array(
                    new XenForo_Phrase('bdapi_using_client'),
                    $client['name'],
                    $client['redirect_uri'],
                    false
                );
            } else {
                $output[$key] = new XenForo_Phrase('viewing_forum_list');
            }
        }

        return $output;
    }

}
