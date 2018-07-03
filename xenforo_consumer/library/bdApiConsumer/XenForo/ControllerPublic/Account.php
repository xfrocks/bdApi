<?php

class bdApiConsumer_XenForo_ControllerPublic_Account extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Account
{
    public function actionContactDetails()
    {
        $response = parent::actionContactDetails();
        if ($response instanceof XenForo_ControllerResponse_View
            && !empty($response->subView)
            && empty($response->subView->params['hasPassword'])
        ) {
            $response->subView->params['bdApiConsumer_providers'] = $this->_bdApiConsumer_getAuthProviders();
        }

        return $response;
    }

    public function actionSecurity()
    {
        $response = parent::actionSecurity();

        if ($response instanceof XenForo_ControllerResponse_View
            && !empty($response->subView)
            && empty($response->subView->params['hasPassword'])
        ) {
            $visitor = XenForo_Visitor::getInstance();

            if (bdApiConsumer_Option::get('takeOver', 'login')) {
                /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
                $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
                $auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations($visitor['user_id']);

                if (!empty($auths)) {
                    foreach ($auths as $auth) {
                        $provider = bdApiConsumer_Option::getProviderByCode($auth['provider']);
                        $link = bdApiConsumer_Helper_Provider::getAccountSecurityLink($provider);

                        if (!empty($link)) {
                            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $link);
                        }
                    }
                }
            }

            $response->subView->params['bdApiConsumer_providers'] = $this->_bdApiConsumer_getAuthProviders();
        }

        return $response;
    }

    public function actionExternalAccounts()
    {
        $response = parent::actionExternalAccounts();

        if ($response instanceof XenForo_ControllerResponse_View
            || empty($response->subView)
        ) {
            // good
        } else {
            // not a view? return it asap
            return $response;
        }

        $visitor = XenForo_Visitor::getInstance();

        /** @var bdApiConsumer_XenForo_Model_UserExternal $externalAuthModel */
        $externalAuthModel = $this->getModelFromCache('XenForo_Model_UserExternal');

        $auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
        if (!$auth) {
            return $this->responseNoPermission();
        }

        $externalAuths = $externalAuthModel->bdApiConsumer_getExternalAuthAssociations($visitor['user_id']);

        $providers = bdApiConsumer_Option::getProviders();

        $viewParams = array(
            'hasPassword' => $auth->hasPassword(),

            'bdApiConsumer_externalAuths' => $externalAuths,
            'bdApiConsumer_providers' => $providers,
        );

        $response->subView->params += $viewParams;

        return $response;
    }

    public function actionExternalAccountsDisassociate()
    {
        return parent::actionExternalAccountsDisassociate();
    }

    public function actionExternal()
    {
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
            XenForo_Link::buildPublicLink('account/external-accounts')
        );
    }

    public function actionExternalNewPassword()
    {
        $session = XenForo_Application::getSession();
        $verified = $session->isRegistered('bdApiConsumer_verified') ? $session->get('bdApiConsumer_verified'): null;
        // 5 minutes
        $verifiedTtl = 5 * 60;

        if (!$verified || ($verified + $verifiedTtl) <= XenForo_Application::$time) {
            return $this->responseNoPermission();
        }

        $visitor = XenForo_Visitor::getInstance();
        $userId = $visitor['user_id'];
        $changeType = $this->_input->filterSingle('type', XenForo_Input::STRING);

        if ($this->isConfirmedPost()) {
            /** @var XenForo_DataWriter_User $writer */
            $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
            $writer->setExistingData($userId);

            if ($changeType === 'email') {
                $newEmail = $this->_input->filterSingle('email', XenForo_Input::STRING);

                /** @var XenForo_Model_UserChangeLog $changeLogModel */
                $changeLogModel = $this->getModelFromCache('XenForo_Model_UserChangeLog');
                $emailChanges = $changeLogModel->countChangeLogsSince(
                    $userId,
                    'email',
                    XenForo_Application::$time - 3600
                );

                if ($emailChanges >= 3) {
                    // only allow 3 email changes within an hour
                    return $this->responseError(new XenForo_Phrase('your_email_may_not_be_changed_at_this_time'));
                }

                $writer->set('email', $newEmail);
            } else {
                $input = $this->_input->filter(array(
                    'password' => XenForo_Input::STRING,
                    'password_confirm' => XenForo_Input::STRING
                ));

                $writer->setPassword($input['password'], $input['password_confirm'], null, true);
            }

            $writer->save();

            if ($session->get('password_date') && $changeType !== 'email') {
                $session->set('password_date', $writer->get('password_date'));
            }

            $session->remove('bdApiConsumer_verified');
            $session->save();

            $redirectTarget = ($changeType === 'email')
                ? $this->_buildLink('account/contact-details')
                : $this->_buildLink('account/security');

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                $redirectTarget
            );
        }

        $viewParams = [
            'changeType' => $changeType
        ];

        $view = $this->responseView(
            'bdApiConsumer_ViewPublic_Account_SecurityUpdate',
            'bdapi_consumer_account_security_update',
            $viewParams
        );

        return $this->_getWrapper('account', 'security', $view);
    }

    public function actionExternalVerify()
    {
        $code = $this->_input->filterSingle('code', XenForo_Input::STRING);
        $provider = bdApiConsumer_Option::getProviderByCode($code);

        if (!$provider) {
            return $this->responseNoPermission();
        }

        $visitor = XenForo_Visitor::getInstance();
        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
        $externalData = $userExternalModel->getExternalAuthAssociationForUser(
            $userExternalModel->bdApiConsumer_getProviderCode($provider),
            $visitor['user_id']
        );

        if (!$externalData) {
            return $this->responseNoPermission();
        }

        $extraData = XenForo_Helper_Php::safeUnserialize($externalData['extra_data']);
        if (empty($extraData['username'])) {
            XenForo_Error::logError(sprintf(
                'Empty username in external auth data. $userId=%d, $extraData=%s',
                $visitor['user_id'],
                json_encode($extraData)
            ));

            return $this->responseError(
                new XenForo_Phrase('bdapi_consumer_cannot_verify_your_account_contact_to_admin')
            );
        }

        $changeType = $this->_input->filterSingle('type', XenForo_Input::STRING);
        if ($changeType !== 'email') {
            $changeType = 'password';
        }

        if ($this->isConfirmedPost()) {
            $password = $this->_input->filterSingle('password', XenForo_Input::STRING);

            /** @var XenForo_Model_Login $loginModel */
            $loginModel = $this->getModelFromCache('XenForo_Model_Login');
            $tooManyAttempts = $loginModel->requireLoginCaptcha('bdapi_consumer_' . $extraData['username']);

            if ($tooManyAttempts) {
                return $this->responseError(
                    new XenForo_Phrase('bdapi_consumer_too_many_login_attempts_to_verify_account')
                );
            }

            $loginModel->logLoginAttempt('bdapi_consumer_' . $extraData['username']);

            $token = bdApiConsumer_Helper_Api::getAccessTokenFromUsernamePassword($provider, $extraData['username'], $password);
            if (!$token) {
                return $this->responseError(new XenForo_Phrase('your_existing_password_is_not_correct'));
            }

            $providerUser = bdApiConsumer_Helper_Api::getVisitor($provider, $token['access_token']);

            if (!$providerUser) {
                return $this->responseError(new XenForo_Phrase('your_existing_password_is_not_correct'));
            }

            if ($providerUser['user_id'] != $extraData['user_id']) {
                XenForo_Error::logError(sprintf(
                    'Invalid user id response from api $responseUserId=%d, $savedUserId=%d',
                    $providerUser['user_id'],
                    $extraData['user_id']
                ));

                return $this->responseError(
                    new XenForo_Phrase('bdapi_consumer_cannot_verify_your_account_contact_to_admin')
                );
            }

            $session = XenForo_Application::getSession();
            $session->set('bdApiConsumer_verified', XenForo_Application::$time);
            $session->save();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                $this->_buildLink('account/external/new-password', null, ['type' => $changeType]),
                new XenForo_Phrase('bdapi_consumer_your_identity_has_been_verified')
            );
        }

        $viewParams = [
            'provider' => $provider,
            'extraData' => $extraData,
            'changeType' => $changeType
        ];

        return $this->responseView(
            'bdApiConsumer_ViewPublic_Account_Security',
            'bdapi_consumer_account_security_verify',
            $viewParams
        );
    }

    protected function _bdApiConsumer_getAuthProviders()
    {
        $visitor = XenForo_Visitor::getInstance();
        $providers = array();

        if (!empty($visitor['externalAuth'])) {
            foreach ($visitor['externalAuth'] as $providerCode => $externalAuthId) {
                $provider = bdApiConsumer_Option::getProviderByCode($providerCode);
                if ($provider) {
                    $providers[$providerCode] = $provider;
                }
            }
        }

        return $providers;
    }
}
