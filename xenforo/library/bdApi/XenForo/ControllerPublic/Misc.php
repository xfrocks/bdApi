<?php

class bdApi_XenForo_ControllerPublic_Misc extends XFCP_bdApi_XenForo_ControllerPublic_Misc
{
    public function actionApiData()
    {
        /* @var $clientModel bdApi_Model_Client */
        $clientModel = $this->getModelFromCache('bdApi_Model_Client');
        /* @var $userScopeModel bdApi_Model_UserScope */
        $userScopeModel = $this->getModelFromCache('bdApi_Model_UserScope');

        $callback = $this->_input->filterSingle('callback', XenForo_Input::STRING);
        $cmd = $this->_input->filterSingle('cmd', XenForo_Input::STRING);
        $clientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
        $data = array();
        $data[$cmd] = 0;

        $client = $clientModel->getClientById($clientId);
        $visitorObj = XenForo_Visitor::getInstance();
        $visitorArray = $visitorObj->toArray();

        if (!empty($client) AND $visitorArray['user_id'] > 0) {
            switch ($cmd) {
                case 'authorized':
                    $scope = $this->_input->filterSingle('scope', XenForo_Input::STRING);
                    $requestedScopes = bdApi_Template_Helper_Core::getInstance()->scopeSplit($scope);
                    if (empty($requestedScopes)) {
                        // no scope requested, check for scope `read`
                        $requestedScopes[] = bdApi_Model_OAuth2::SCOPE_READ;
                    }
                    $requestedScopesAccepted = array();

                    if ($data[$cmd] === 0 AND $clientModel->canAutoAuthorize($client, $scope)) {
                        // this client has auto authorize setting for the requested scope
                        // response with authorized = 1
                        // note: we don't have (and don't need) an access token for now
                        // but in case the client application request authorization, it
                        // will be granted automatically anyway
                        $requestedScopesAccepted = $requestedScopes;
                        $data[$cmd] = 1;
                    }

                    if ($data[$cmd] === 0) {
                        // start looking for accepted scopes
                        $userScopes = $userScopeModel->getUserScopes($client['client_id'], $visitorArray['user_id']);

                        foreach ($requestedScopes as $scope) {
                            foreach ($userScopes as $userScope) {
                                if ($userScope['scope'] === $scope) {
                                    $requestedScopesAccepted[] = $scope;
                                }
                            }
                        }

                        if (count($requestedScopes) === count($requestedScopesAccepted)) {
                            $data[$cmd] = 1;
                        }
                    }

                    if ($data[$cmd] === 1) {
                        if (!empty($scope)) {
                            // some actual scopes were requested, return user data according to those scopes
                            $session = new bdApi_Session();
                            $session->fakeStart($client, $visitorObj, $requestedScopesAccepted);

                            $visitorPrepared = bdApi_Data_Helper_Core::filter(
                                $visitorArray,
                                $this->_getFilterPublicKeysForVisitorData()
                            );
                            $data = array_merge($visitorPrepared, $data);
                        } else {
                            // just checking for connection status, return user_id only
                            $data['user_id'] = $visitorArray['user_id'];
                        }
                    }

                    // switch ($cmd)
                    break;
            }
        }

        $clientModel->signApiData($client, $data);

        $viewParams = array(
            'callback' => $callback,
            'cmd' => $cmd,
            'client_id' => $clientId,
            'data' => $data,
        );

        $this->_routeMatch->setResponseType('raw');

        return $this->responseView('bdApi_ViewPublic_Misc_Api_Data', '', $viewParams);
    }

    protected function _getFilterPublicKeysForVisitorData()
    {
        return array(
            'user_id' => 'user_id',
            'username' => 'username',
            'alerts_unread' => 'user_unread_notification_count',
            'conversations_unread' => 'user_unread_conversation_count',
        );
    }

    protected function _checkCsrf($action)
    {
        if ($action === 'ApiData') {
            return;
        }

        parent::_checkCsrf($action);
    }
}
