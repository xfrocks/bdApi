<?php

class bdApiConsumer_XenForo_ControllerPublic_Register extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Register
{
    const SESSION_KEY_REDIRECT = 'bdApiConsumer_redirect';

    public function actionExternal()
    {
        $providerCode = $this->_input->filterSingle('provider', XenForo_Input::STRING);
        $assocUserId = $this->_input->filterSingle('assoc', XenForo_Input::UINT);
        $externalCode = $this->_input->filterSingle('code', XenForo_Input::STRING);
        $redirect = $this->_bdApiConsumer_getRedirect();

        $state = $this->_input->filterSingle('state', XenForo_Input::STRING);
        if (!empty($state)) {
            // looks like bdApiConsumer_Option::CONFIG_TRACK_AUTHORIZE_URL_STATE has been enabled
            // attempt to unpack the state data now
            $stateData = @base64_decode($state);
            if ($stateData !== false) {
                $stateData = @json_decode($stateData, true, 2);
                if ($stateData !== null) {
                    if (isset($stateData['time'])) {
                        $stateData['timeElapsed'] = XenForo_Application::$time - $stateData['time'];
                    }

                    // make it available in server error log (if an error occurs)
                    $_POST['.state'] = $stateData;
                }
            }
        }

        $provider = bdApiConsumer_Option::getProviderByCode($providerCode);
        if (empty($provider)) {
            if (!empty($externalCode)) {
                // make this available in server error log
                $_POST['.dynamicRedirect'] = $this->getDynamicRedirect();

                // this is one serious error
                throw new XenForo_Exception('Provider could not be determined');
            } else {
                return $this->responseNoPermission();
            }
        }

        $externalRedirectUri = XenForo_Link::buildPublicLink('canonical:register/external', false, array(
            'provider' => $providerCode,
            'assoc' => ($assocUserId ? $assocUserId : false),
        ));

        if ($this->_input->filterSingle('reg', XenForo_Input::UINT)) {
            XenForo_Application::get('session')->set(self::SESSION_KEY_REDIRECT, $redirect);

            $social = $this->_input->filterSingle('social', XenForo_Input::STRING);
            $requestUrl = bdApiConsumer_Helper_Api::getRequestUrl($provider,
                $externalRedirectUri, array('social' => $social));

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL, $requestUrl);
        }

        $externalToken = null;

        if (empty($externalToken)) {
            $_token = $this->_input->filterSingle('_token', XenForo_Input::STRING);
            if (!empty($_token)) {
                $_token = @base64_decode($_token);
                if (!empty($_token)) {
                    $_token = @json_decode($_token, true);
                    if (!empty($_token)) {
                        $externalToken = $_token;
                    }
                }
            }
        }

        if (empty($externalToken)) {
            // there should be `code` at this point...
            if (empty($externalCode)) {
                return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x',
                    array('provider' => $provider['name'])));
            }

            $externalToken = bdApiConsumer_Helper_Api::getAccessTokenFromCode($provider,
                $externalCode, $externalRedirectUri);

            if (!empty($externalToken)) {
                $selfRedirect = $this->_request->getRequestUri();
                $selfRedirect = preg_replace('#(\?|&)code=.+(&|$)#', '$1', $selfRedirect);
                $selfRedirect = preg_replace('#(\?|&)state=.+(&|$)#', '$1', $selfRedirect);

                // filter $externalToken keys to make it more lightweight
                foreach (array_keys($externalToken) as $_key) {
                    if ($_key === 'debug'
                        || substr($_key, 0, 1) === '_'
                    ) {
                        unset($externalToken[$_key]);
                    }
                }
                $selfRedirect .= sprintf('%1$s_token=%2$s',
                    strpos($selfRedirect, '?') === false ? '?' : '&',
                    rawurlencode(base64_encode(json_encode($externalToken))));

                // do a self redirect immediately so user won't refresh the page
                // TODO: improve this
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::SUCCESS,
                    $selfRedirect
                );
            }
        }

        if (empty($externalToken)) {
            if (!XenForo_Visitor::getUserId()) {
                // report error only if user hasn't been logged in
                return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x',
                    array('provider' => $provider['name'])));
            } else {
                // or try to be friendly and just redirect user back to where s/he was
                return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
            }
        }

        $externalVisitor = bdApiConsumer_Helper_Api::getVisitor($provider, $externalToken['access_token']);
        if (empty($externalVisitor)) {
            return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x',
                array('provider' => $provider['name'])));
        }
        if (empty($externalVisitor['user_email'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_returned_unknown_error',
                array('provider' => $provider['name'])));
        }
        if (isset($externalVisitor['user_is_valid'])
            && isset($externalVisitor['user_is_verified'])
        ) {
            if (empty($externalVisitor['user_is_valid'])
                || empty($externalVisitor['user_is_verified'])
            ) {
                return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_account_not_good_standing',
                    array('provider' => $provider['name'])));
            }
        }

        $userModel = $this->_getUserModel();
        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $userExternalModel = $this->_getUserExternalModel();

        $existingAssoc = $userExternalModel->getExternalAuthAssociation(
            $userExternalModel->bdApiConsumer_getProviderCode($provider), $externalVisitor['user_id']);
        $autoRegistered = false;

        if (empty($existingAssoc)) {
            $existingAssoc = $this->_bdApiConsumer_autoRegister($provider, $externalToken, $externalVisitor);
            if (!empty($existingAssoc)) {
                $autoRegistered = true;
            }
        }

        if ($existingAssoc
            && $userModel->getUserById($existingAssoc['user_id'])
        ) {
            XenForo_Application::get('session')->changeUserId($existingAssoc['user_id']);
            XenForo_Visitor::setup($existingAssoc['user_id']);

            if (!$autoRegistered) {
                $userExternalModel->bdApiConsumer_updateExternalAuthAssociation($provider,
                    $externalVisitor['user_id'], $existingAssoc['user_id'],
                    array_merge($externalVisitor, array('token' => $externalToken)));
            }

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
        }

        $existingUser = false;
        $emailMatch = false;
        if (XenForo_Visitor::getUserId()) {
            $existingUser = XenForo_Visitor::getInstance();
        } elseif ($assocUserId) {
            $existingUser = $userModel->getUserById($assocUserId);
        }

        if (!$existingUser) {
            $existingUser = $userModel->getUserByEmail($externalVisitor['user_email']);
            $emailMatch = true;
        }

        if ($existingUser) {
            // must associate: matching user
            return $this->responseView('bdApiConsumer_ViewPublic_Register_External',
                'bdapi_consumer_register',
                array(
                    'associateOnly' => true,

                    'provider' => $provider,
                    'externalToken' => $externalToken,
                    'externalVisitor' => $externalVisitor,

                    'existingUser' => $existingUser,
                    'emailMatch' => $emailMatch,
                    'redirect' => $redirect
                )
            );
        }

        if (bdApiConsumer_Option::get('bypassRegistrationActive')) {
            // do not check for registration active option
        } else {
            $this->_assertRegistrationActive();
        }

        $externalVisitor['username']
            = bdApiConsumer_Helper_AutoRegister::suggestUserName($externalVisitor['username'], $userModel);

        return $this->responseView(
            'bdApiConsumer_ViewPublic_Register_External',
            'bdapi_consumer_register',
            array(
                'provider' => $provider,
                'externalToken' => $externalToken,
                'externalVisitor' => $externalVisitor,
                'redirect' => $redirect,

                'customFields' => $this->_getFieldModel()->prepareUserFields(
                    $this->_getFieldModel()->getUserFields(array('registration' => true)), true),

                'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
                'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
            ),
            $this->_getRegistrationContainerParams()
        );
    }

    public function actionExternalRegister()
    {
        $this->_assertPostOnly();

        $redirect = $this->_bdApiConsumer_getRedirect();

        $userModel = $this->_getUserModel();
        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $userExternalModel = $this->_getUserExternalModel();

        $providerCode = $this->_input->filterSingle('provider', XenForo_Input::STRING);
        $provider = bdApiConsumer_Option::getProviderByCode($providerCode);
        if (empty($provider)) {
            return $this->responseNoPermission();
        }

        $doAssoc = ($this->_input->filterSingle('associate', XenForo_Input::STRING)
            || $this->_input->filterSingle('force_assoc', XenForo_Input::UINT));
        $userId = 0;
        if ($doAssoc) {
            $associate = $this->_input->filter(array(
                'associate_login' => XenForo_Input::STRING,
                'associate_password' => XenForo_Input::STRING
            ));

            $loginModel = $this->_getLoginModel();

            if ($loginModel->requireLoginCaptcha($associate['associate_login'])) {
                return $this->responseError(
                    new XenForo_Phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
            }

            $userId = $userModel->validateAuthentication($associate['associate_login'],
                $associate['associate_password'], $error);
            if (!$userId) {
                $loginModel->logLoginAttempt($associate['associate_login']);
                return $this->responseError($error);
            }
        }

        $refreshToken = $this->_input->filterSingle('refresh_token', XenForo_Input::STRING);
        $externalToken = bdApiConsumer_Helper_Api::getAccessTokenFromRefreshToken($provider, $refreshToken);
        if (empty($externalToken)) {
            return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x',
                array('provider' => $provider['name'])));
        }

        $externalVisitor = bdApiConsumer_Helper_Api::getVisitor($provider, $externalToken['access_token']);
        if (empty($externalVisitor)) {
            return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x',
                array('provider' => $provider['name'])));
        }
        if (empty($externalVisitor['user_email'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_returned_unknown_error',
                array('provider' => $provider['name'])));
        }
        if (isset($externalVisitor['user_is_valid']) AND isset($externalVisitor['user_is_verified'])) {
            if (empty($externalVisitor['user_is_valid']) OR empty($externalVisitor['user_is_verified'])) {
                return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_account_not_good_standing',
                    array('provider' => $provider['name'])));
            }
        }

        if ($doAssoc) {
            $userExternalModel->bdApiConsumer_updateExternalAuthAssociation($provider,
                $externalVisitor['user_id'], $userId,
                array_merge($externalVisitor, array('token' => $externalToken)));

            XenForo_Application::getSession()->changeUserId($userId);
            XenForo_Visitor::setup($userId);

            return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
        }

        if (bdApiConsumer_Option::get('bypassRegistrationActive')) {
            // do not check for registration active option
        } else {
            $this->_assertRegistrationActive();
        }

        $data = $this->_input->filter(array(
            'username' => XenForo_Input::STRING,
            'timezone' => XenForo_Input::STRING,
        ));

        // TODO: custom fields

        if (XenForo_Dependencies_Public::getTosUrl()
            && !$this->_input->filterSingle('agree', XenForo_Input::UINT)
        ) {
            return $this->responseError(new XenForo_Phrase('you_must_agree_to_terms_of_service'));
        }

        $user = bdApiConsumer_Helper_AutoRegister::createUser($data, $provider,
            $externalToken, $externalVisitor, $this->_getUserExternalModel());

        XenForo_Application::getSession()->changeUserId($user['user_id']);
        XenForo_Visitor::setup($user['user_id']);

        $viewParams = array(
            'user' => $user,
            'redirect' => $redirect,
        );

        return $this->responseView(
            'XenForo_ViewPublic_Register_Process',
            'register_process',
            $viewParams,
            $this->_getRegistrationContainerParams()
        );
    }

    protected function _bdApiConsumer_autoRegister($provider, $externalToken, array $externalVisitor)
    {
        $mode = bdApiConsumer_Option::get('autoRegister');

        if ($mode !== 'on' AND $mode !== 'id_sync') {
            // not in working mode
            return false;
        }

        $data = array();

        $sameName = $this->_getUserModel()->getUserByName($externalVisitor['username']);
        if (!empty($sameName)) {
            // username conflict found, too bad
            return false;
        }
        $data['username'] = $externalVisitor['username'];

        if ($mode === 'id_sync') {
            // additionally look for user with same ID
            $sameId = $this->_getUserModel()->getUserById($externalVisitor['user_id']);
            if (!empty($sameId)) {
                // ID conflict found...
                return false;
            }
            $data['user_id'] = $externalVisitor['user_id'];
        }

        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $userExternalModel = $this->_getUserExternalModel();
        $user = bdApiConsumer_Helper_AutoRegister::createUser($data, $provider,
            $externalToken, $externalVisitor, $userExternalModel);

        if (empty($user)) {
            // for some reason, the user could not be created
            return false;
        }

        return $userExternalModel->getExternalAuthAssociation(
            $userExternalModel->bdApiConsumer_getProviderCode($provider), $externalVisitor['user_id']);
    }

    protected function _bdApiConsumer_getRedirect()
    {
        $redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

        if (empty($redirect)) {
            $redirect = XenForo_Application::getSession()->get(self::SESSION_KEY_REDIRECT);
        }

        if (empty($redirect)) {
            $redirect = XenForo_Link::convertUriToAbsoluteUri($this->getDynamicRedirect());
        }

        return $redirect;
    }

}
