<?php

class bdApi_XenForo_ControllerPublic_Account extends XFCP_bdApi_XenForo_ControllerPublic_Account
{
    public function actionApi()
    {
        $visitor = XenForo_Visitor::getInstance();

        /* @var $tokenModel bdApi_Model_Token */
        $tokenModel = $this->getModelFromCache('bdApi_Model_Token');
        /* @var $userScopeModel bdApi_Model_UserScope */
        $userScopeModel = $this->getModelFromCache('bdApi_Model_UserScope');

        $clients = $this->_bdApi_getClientModel()->getClients(array('user_id' => XenForo_Visitor::getUserId()), array());
        $tokens = $tokenModel->getTokens(array('user_id' => XenForo_Visitor::getUserId()));
        $userScopes = $userScopeModel->getUserScopesForAllClients(XenForo_Visitor::getUserId());

        $userScopesByClientIds = array();
        foreach ($userScopes as $userScope) {
            if (!isset($userScopesByClientIds[$userScope['client_id']])) {
                $userScopesByClientIds[$userScope['client_id']] = array(
                    'last_issue_date' => 0,
                    'user_scopes' => array(),
                    'client' => $userScope,
                );
            }

            $userScopesByClientIds[$userScope['client_id']]['last_issue_date'] = max($userScopesByClientIds[$userScope['client_id']]['last_issue_date'], $userScope['accept_date']);
            $userScopesByClientIds[$userScope['client_id']]['user_scopes'][$userScope['scope']] = $userScope;
        }

        foreach ($tokens as $token) {
            if (empty($userScopesByClientIds[$token['client_id']])) {
                continue;
            }

            $userScopesByClientIds[$token['client_id']]['last_issue_date'] = max($userScopesByClientIds[$token['client_id']]['last_issue_date'], $token['issue_date']);
        }

        $viewParams = array(
            'clients' => $clients,
            'userScopesByClientIds' => $userScopesByClientIds,

            'permClientNew' => $visitor->hasPermission('general', 'bdApi_clientNew'),
        );

        return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Api_Index', 'bdapi_account_api', $viewParams));
    }

    public function actionApiClientAdd()
    {
        $visitor = XenForo_Visitor::getInstance();
        if (!$visitor->hasPermission('general', 'bdApi_clientNew')) {
            return $this->responseNoPermission();
        }

        $viewParams = array(
            'client' => array(),
        );

        return $this->_getWrapper(
            'account', 'api',
            $this->responseView('bdApi_ViewPublic_Account_Api_Client_Edit', 'bdapi_account_api_client_edit', $viewParams));
    }

    public function actionApiClientEdit()
    {
        $client = $this->_bdApi_getClientOrError();

        $viewParams = array(
            'client' => $client,
        );

        return $this->_getWrapper(
            'account', 'api',
            $this->responseView('bdApi_ViewPublic_Account_Api_Client_Edit', 'bdapi_account_api_client_edit', $viewParams)
        );
    }

    public function actionApiClientSave()
    {
        $this->_assertPostOnly();

        $client = null;
        $options = array();
        try {
            $client = $this->_bdApi_getClientOrError();
            $options = $client['options'];
        } catch (Exception $e) {
            // ignore
        }

        $dwInput = $this->_input->filter(array(
            'name' => XenForo_Input::STRING,
            'description' => XenForo_Input::STRING,
            'redirect_uri' => XenForo_Input::STRING,
        ));

        $optionsInput = $this->_input->filterSingle('options', XenForo_Input::ARRAY_SIMPLE);
        $optionsInput = array_merge(array(
            'whitelisted_domains' => '',
            'public_key' => '',
        ), $options, $optionsInput);

        $dw = XenForo_DataWriter::create('bdApi_DataWriter_Client');
        if (!empty($client)) {
            $dw->setExistingData($client, true);
        } else {
            $dw->set('client_id', $this->_bdApi_getClientModel()->generateClientId());
            $dw->set('client_secret', $this->_bdApi_getClientModel()->generateClientSecret());
            $dw->set('user_id', XenForo_Visitor::getUserId());
        }

        $dw->bulkSet($dwInput);
        $dw->set('options', $optionsInput);

        $dw->save();

        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CREATED, XenForo_Link::buildPublicLink('account/api'));
    }

    public function actionApiClientDelete()
    {
        $client = $this->_bdApi_getClientOrError();

        if ($this->_request->isPost()) {
            $dw = XenForo_DataWriter::create('bdApi_DataWriter_Client');
            $dw->setExistingData($client, true);
            $dw->delete();

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED, XenForo_Link::buildPublicLink('account/api'));
        } else {
            $viewParams = array('client' => $client);

            return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Api_Client_Delete', 'bdapi_account_api_client_delete', $viewParams));
        }
    }

    public function actionApiUpdateScope()
    {
        $visitor = XenForo_Visitor::getInstance();

        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

        $client = $this->_bdApi_getClientOrError(false);

        $userScopes = $oauth2Model->getUserScopeModel()->getUserScopes($client['client_id'], $visitor['user_id']);
        if (empty($userScopes)) {
            return $this->responseNoPermission();
        }

        if ($this->isConfirmedPost()) {
            $isRevoke = $this->_input->filterSingle('revoke', XenForo_Input::STRING);
            $isRevoke = !empty($isRevoke);

            XenForo_Db::beginTransaction();

            try {
                $scopes = $this->_input->filterSingle('scopes', XenForo_Input::STRING, array('array' => true));
                if (empty($scopes)) {
                    // no scopes are selected, that equals revoking
                    $isRevoke = true;
                }
                $userScopesChanged = false;

                foreach ($userScopes as $userScope) {
                    if ($isRevoke OR !in_array($userScope['scope'], $scopes, true)) {
                        // remove the accepted user scope
                        $oauth2Model->getUserScopeModel()->deleteUserScope($client['client_id'], $visitor['user_id'], $userScope['scope']);
                        $userScopesChanged = true;
                    }
                }

                if ($userScopesChanged) {
                    // invalidate all existing tokens
                    $oauth2Model->getAuthCodeModel()->deleteAuthCodes($client['client_id'], $visitor['user_id']);
                    $oauth2Model->getRefreshTokenModel()->deleteRefreshTokens($client['client_id'], $visitor['user_id']);
                    $oauth2Model->getTokenModel()->deleteTokens($client['client_id'], $visitor['user_id']);
                }

                if ($isRevoke) {
                    // unsubscribe for user and notification
                    $oauth2Model->getSubscriptionModel()->deleteSubscriptions($client['client_id'], bdApi_Model_Subscription::TYPE_USER, $visitor['user_id']);
                    $oauth2Model->getSubscriptionModel()->deleteSubscriptions($client['client_id'], bdApi_Model_Subscription::TYPE_NOTIFICATION, $visitor['user_id']);
                }

                XenForo_Db::commit();
            } catch (Exception $e) {
                XenForo_Db::rollback();
                throw $e;
            }

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED, XenForo_Link::buildPublicLink('account/api'));
        } else {
            $viewParams = array(
                'client' => $client,
                'userScopes' => $userScopes,
            );

            return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Api_UpdateScope', 'bdapi_account_api_update_scope', $viewParams));
        }
    }

    public function actionAuthorize()
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

        $authorizeParams = $this->_input->filter($oauth2Model->getAuthorizeParamsInputFilter());

        if ($this->_request->isPost()) {
            // allow user to deny some certain scopes
            // only when this is a POST request, this should keep us safe from some vectors
            // of attack
            $scopesIncluded = $this->_input->filterSingle('scopes_included', XenForo_Input::UINT);
            $scopes = $this->_input->filterSingle('scopes', XenForo_Input::ARRAY_SIMPLE);
            if (!empty($scopesIncluded)) {
                $authorizeParams['scope'] = bdApi_Template_Helper_Core::getInstance()->scopeJoin($scopes);
            }
        }

        $client = $oauth2Model->getClientModel()->getClientById($authorizeParams['client_id']);
        if (empty($client)) {
            throw new XenForo_Exception(new XenForo_Phrase('bdapi_authorize_error_client_x_not_found', array('client' => $authorizeParams['client_id'])));
        }

        // sondh@2013-03-19
        // this is a non-standard implementation: bypass confirmation dialog if the
        // client has appropriate option set
        $bypassConfirmation = false;
        if ($oauth2Model->getClientModel()->canAutoAuthorize($client, $authorizeParams['scope'])) {
            $bypassConfirmation = true;
        }

        // sondh@2014-09-26
        // bypass confirmation if all requested scopes have been granted at some point
        // in old version of this add-on, it checked for scope from active tokens
        // from now on, we look for all scopes (no expiration) for better user experience
        // if a token expires, it should not invalidate all user's choices
        $userScopes = $oauth2Model->getUserScopeModel()->getUserScopes($client['client_id'], XenForo_Visitor::getUserId());
        $paramScopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($authorizeParams['scope']);
        $paramScopesNew = array();
        foreach ($paramScopes as $paramScope) {
            if (!isset($userScopes[$paramScope])) {
                $paramScopesNew[] = $paramScope;
            }
        }
        if (empty($paramScopesNew)) {
            $bypassConfirmation = true;
        } else {
            $authorizeParams['scope'] = bdApi_Template_Helper_Core::getInstance()->scopeJoin($paramScopesNew);
        }

        $response = $oauth2Model->getServer()->actionOauthAuthorize1($this, $authorizeParams);
        if (is_object($response) && $response instanceof XenForo_ControllerResponse_Abstract) {
            return $response;
        }

        if ($this->_request->isPost() || $bypassConfirmation) {
            $accept = $this->_input->filterSingle('accept', XenForo_Input::STRING);
            $accepted = !!$accept;

            if ($bypassConfirmation) {
                // sondh@2013-03-19
                // of course if the dialog was bypassed, $accepted should be true
                $accepted = true;
            }

            if ($accepted) {
                // sondh@2014-09-26
                // get all up to date user scopes and include in the new token
                // that means client only need to ask for a scope once and they will always have
                // that scope in future authorizations, even if they ask for less scope!
                // making it easy for client dev, they don't need to track whether they requested
                // a scope before. Just check the most recent token for that information.
                $paramScopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($authorizeParams['scope']);
                foreach ($userScopes as $userScope => $userScopeInfo) {
                    if (!in_array($userScope, $paramScopes, true)) {
                        $paramScopes[] = $userScope;
                    }
                }
                $paramScopes = array_unique($paramScopes);
                asort($paramScopes);
                $authorizeParams['scope'] = bdApi_Template_Helper_Core::getInstance()->scopeJoin($paramScopes);
            }

            return $oauth2Model->getServer()->actionOauthAuthorize2($this, $authorizeParams, $accepted, XenForo_Visitor::getUserId());
        } else {
            $viewParams = array(
                'client' => $client,
                'authorizeParams' => $authorizeParams,
            );

            return $this->_getWrapper('account', 'api', $this->responseView('bdApi_ViewPublic_Account_Authorize', 'bdapi_account_authorize', $viewParams));
        }
    }

    public function actionApiData()
    {
        return $this->responseReroute('XenForo_ControllerPublic_Misc', 'api-data');
    }

    protected function _preDispatch($action)
    {
        try {
            parent::_preDispatch($action);
        } catch (XenForo_ControllerResponse_Exception $e) {
            if ($action === 'Authorize') {
                // this is our action and an exception is thrown
                // check to see if it is a registrationRequired error
                $controllerResponse = $e->getControllerResponse();
                if ($controllerResponse instanceof XenForo_ControllerResponse_Reroute AND $controllerResponse->controllerName == 'XenForo_ControllerPublic_Error' AND $controllerResponse->action == 'registrationRequired') {
                    // so it is...
                    $requestPaths = XenForo_Application::get('requestPaths');
                    $session = XenForo_Application::getSession();
                    $session->set('bdApi_authorizePending', $requestPaths['fullUri']);

                    $controllerResponse->action = 'authorizeGuest';
                }
            }

            throw $e;
        }
    }

    protected function _bdApi_getClientOrError($verifyCanEdit = true)
    {
        $clientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
        if (empty($clientId)) {
            throw $this->getNoPermissionResponseException();
        }

        $client = $this->_bdApi_getClientModel()->getClientByid($clientId);
        if (empty($client)) {
            throw $this->getNoPermissionResponseException();
        }

        if ($verifyCanEdit AND $client['user_id'] != XenForo_Visitor::getUserId()) {
            throw $this->getNoPermissionResponseException();
        }

        return $client;
    }

    /**
     * @return bdApi_Model_Client
     */
    protected function _bdApi_getClientModel()
    {
        return $this->getModelFromCache('bdApi_Model_Client');
    }
}
