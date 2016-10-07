<?php

class bdApi_ControllerApi_Index extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();

        $visitor = XenForo_Visitor::getInstance();

        $systemInfo = array();
        if ($session->getOAuthClientId() === '') {
            $systemInfo += array(
                'oauth/authorize' => bdApi_Data_Helper_Core::safeBuildApiLink('oauth/authorize', array(), array('oauth_token' => '')),
                'oauth/token' => bdApi_Data_Helper_Core::safeBuildApiLink('oauth/token', array(), array('oauth_token' => '')),
            );
        }
        if ($session->checkScope(bdApi_Model_OAuth2::SCOPE_POST)) {
            $systemInfo = array(
                // YYYYMMDD and 2 digits number (01-99), allowing maximum 99 revisions/day
                'api_revision' => 2016062001,
                'api_modules' => $this->_getModules(),
            );
        }

        $data = array();
        $data['links'] = array(
            'search' => bdApi_Data_Helper_Core::safeBuildApiLink('search'),
            'navigation' => bdApi_Data_Helper_Core::safeBuildApiLink('navigation', array(), array('parent' => 0)),
            'threads/recent' => bdApi_Data_Helper_Core::safeBuildApiLink('threads/recent'),
            'users' => bdApi_Data_Helper_Core::safeBuildApiLink('users'),
        );

        if ($visitor['user_id'] > 0) {
            $data['links']['conversations'] = bdApi_Data_Helper_Core::safeBuildApiLink('conversations');
            $data['links']['forums/followed'] = bdApi_Data_Helper_Core::safeBuildApiLink('forums/followed');
            $data['links']['notifications'] = bdApi_Data_Helper_Core::safeBuildApiLink('notifications');
            $data['links']['threads/followed'] = bdApi_Data_Helper_Core::safeBuildApiLink('threads/followed');
            $data['links']['threads/new'] = bdApi_Data_Helper_Core::safeBuildApiLink('threads/new');
            $data['links']['users/ignored'] = bdApi_Data_Helper_Core::safeBuildApiLink('users/ignored');
            $data['links']['users/me'] = bdApi_Data_Helper_Core::safeBuildApiLink('users', array(
                'user_id' => XenForo_Visitor::getInstance()->toArray()), array('oauth_token' => ''));

            if ($visitor->canUpdateStatus()) {
                $data['post']['status'] = bdApi_Data_Helper_Core::safeBuildApiLink('users/me/timeline');
            }
        }

        $data['system_info'] = $systemInfo;

        return $this->responseData('bdApi_ViewApi_Index', $data);
    }

    protected function _getModules()
    {
        $modules = array(
            'forum' => 2016091201,
            'oauth2' => 2016030902,
            'subscription' => 2014092301,
        );

        $session = bdApi_Data_Helper_Core::safeGetSession();
        if (!!$session->getOAuthClientOption('allow_search_indexing')) {
            $modules['search/indexing'] = 2015091601;
        }
        if (!!$session->getOAuthClientOption('allow_user_0_subscription')) {
            $modules['subscriptions?hub_topic=user_0'] = 2016100501;
        }

        return $modules;
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
