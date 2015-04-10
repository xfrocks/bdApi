<?php

class bdApi_ControllerApi_Tool extends bdApi_ControllerApi_Abstract
{
    public function actionGetLogin()
    {
        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();

        $input = $this->_input->filter(array(
            'oauth_token' => XenForo_Input::STRING,
            'redirect_uri' => XenForo_Input::STRING,
        ));

        if (empty($input['redirect_uri'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_tools_login_requires_redirect_uri'), 400);
        }
        if (!$session->isValidRedirectUri($input['redirect_uri'])) {
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

        if ($options->get('facebookAppId')) {
            $social[] = 'facebook';
        }

        if ($options->get('twitterAppKey')) {
            $social[] = 'twitter';
        }

        if ($options->get('googleClientId')) {
            $social[] = 'google';
        }

        return $this->responseData('bdApi_ViewApi_Tool_LoginSocial', array('social' => $social));
    }

    public function actionGetLogout()
    {
        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();

        $input = $this->_input->filter(array(
            'oauth_token' => XenForo_Input::STRING,
            'redirect_uri' => XenForo_Input::STRING,
        ));

        if (empty($input['redirect_uri'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_tools_login_requires_redirect_uri'), 400);
        }
        if (!$session->isValidRedirectUri($input['redirect_uri'])) {
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

        /* @var $userConfirmationModel XenForo_Model_UserConfirmation */
        $userConfirmationModel = $this->getModelFromCache('XenForo_Model_UserConfirmation');
        $userConfirmationModel->sendPasswordResetRequest($user);

        return $this->responseMessage(new XenForo_Phrase('password_reset_request_has_been_emailed_to_you'));
    }

    public function actionPostLink()
    {
        $type = $this->_input->filterSingle('type', XenForo_Input::STRING, array('default' => 'public'));
        $route = $this->_input->filterSingle('route', XenForo_Input::STRING, array('default' => 'index'));

        switch ($type) {
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

    public function actionGetParseLink()
    {
        $link = $this->_input->filterSingle('link', XenForo_Input::STRING);
        $link = XenForo_Link::convertUriToAbsoluteUri($link, true);
        $fc = XenForo_Application::get('_bdApi_fc');
        /* @var $dependencies bdApi_Dependencies */
        $dependencies = $fc->getDependencies();

        $request = new bdApi_Zend_Controller_Request_Http($link);
        $request->setBaseUrl(parse_url(XenForo_Application::getOptions()->get('boardUrl'), PHP_URL_PATH));

        $routeMatch = $dependencies->routePublic($request);
        if (!$routeMatch OR !$routeMatch->getControllerName()) {
            // link cannot be route
            return $this->_actionGetParseLink_getControllerResponseNop($link, false);
        }

        $controllerResponse = $this->_actionGetParseLink_getControllerResponse($link, $request, $routeMatch);
        if (!empty($controllerResponse)) {
            return $controllerResponse;
        }

        // controller / action not recognized...
        return $this->_actionGetParseLink_getControllerResponseNop($link, true);
    }

    protected function _actionGetParseLink_getControllerResponseNop($link, $routed)
    {
        return $this->responseData('bdApi_ViewApi_Tool_ParseLink', array(
            'link' => $link,
            'routed' => $routed,
        ));
    }

    protected function _actionGetParseLink_getControllerResponse($link, Zend_Controller_Request_Http $request, XenForo_RouteMatch $routeMatch)
    {
        switch ($routeMatch->getControllerName()) {
            case 'XenForo_ControllerPublic_Forum':
                $nodeId = $request->getParam('node_id');

                if (empty($nodeId)) {
                    $nodeName = $request->getParam('node_name');
                    if (!empty($nodeName)) {
                        /* @var $nodeModel XenForo_Model_Node */
                        $nodeModel = $this->getModelFromCache('XenForo_Model_Node');
                        $node = $nodeModel->getNodeByName($nodeName, 'Forum');
                        if (!empty($node)) {
                            $nodeId = $node['node_id'];
                        }
                    }
                }

                if (!empty($nodeId)) {
                    $this->_request->setParam('forum_id', $nodeId);
                }

                return $this->responseReroute('bdApi_ControllerApi_Thread', 'get-index');
            case 'XenForo_ControllerPublic_Thread':
                $threadId = $request->getParam('thread_id');

                if (!empty($threadId)) {
                    $this->_request->setParam('thread_id', $threadId);

                    $linkFragment = parse_url($link, PHP_URL_FRAGMENT);
                    if (!empty($linkFragment) AND preg_match('#^post-(?<post_id>\d+)$#', $linkFragment, $fragment)) {
                        $this->_request->setParam('page_of_post_id', $fragment['post_id']);
                    }

                    return $this->responseReroute('bdApi_ControllerApi_Post', 'get-index');
                }
                break;
            case 'XenForo_ControllerPublic_Post':
                $postId = $request->getParam('post_id');

                if (!empty($postId)) {
                    $this->_request->setParam('page_of_post_id', $postId);
                    return $this->responseReroute('bdApi_ControllerApi_Post', 'get-index');
                }
                break;
        }

        return null;
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
