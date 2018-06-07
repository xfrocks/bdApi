<?php

class bdApiConsumer_XenForo_ControllerPublic_Login extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Login
{
    protected $_bdApiConsumer_beforeLoginVisitorId = 0;

    public function bdApiConsumer_getBeforeLoginVisitorId()
    {
        return $this->_bdApiConsumer_beforeLoginVisitorId;
    }

    protected function _preDispatch($action)
    {
        $this->_bdApiConsumer_beforeLoginVisitorId = XenForo_Visitor::getUserId();

        parent::_preDispatch($action);
    }

    public function actionExternal()
    {
        $this->_assertPostOnly();

        if (XenForo_Application::isRegistered('_bdCloudServerHelper_readonly')) {
            // disable external login if [bd] Cloud Server Helper Read Only mode is turned on
            return $this->responseNoPermission();
        }

        $providerCode = $this->_input->filterSingle('provider', XenForo_Input::STRING);
        $provider = bdApiConsumer_Option::getProviderByCode($providerCode);
        if (empty($provider)) {
            return $this->responseNoPermission();
        }

        $externalUserId = $this->_input->filterSingle('external_user_id', XenForo_Input::UINT);
        if (empty($externalUserId)) {
            return $this->responseNoPermission();
        }

        if (!bdApiConsumer_Helper_Api::verifyJsSdkSignature($provider, $_REQUEST)) {
            return $this->responseNoPermission();
        }

        $userModel = $this->_getUserModel();
        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');

        $existingAssoc = $userExternalModel->getExternalAuthAssociation(
            $userExternalModel->bdApiConsumer_getProviderCode($provider),
            $externalUserId
        );

        if (!empty($existingAssoc)) {
            $accessToken = $userExternalModel->bdApiConsumer_getAccessTokenFromAuth($provider, $existingAssoc);
            if (empty($accessToken)) {
                // no access token in the auth, consider no auth at all
                $existingAssoc = null;
            }
        }

        if (empty($existingAssoc)) {
            $autoRegister = bdApiConsumer_Option::get('autoRegister');

            if ($autoRegister === 'on' OR $autoRegister === 'id_sync') {
                // we have to do a refresh here
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::SUCCESS,
                    XenForo_Link::buildPublicLink('canonical:register/external', null, array(
                        'provider' => $providerCode,
                        'reg' => 1,
                        'redirect' => $this->getDynamicRedirect(),
                    )),
                    new XenForo_Phrase(
                        'bdapi_consumer_being_auto_login_auto_register_x',
                        array('provider' => $provider['name'])
                    )
                );
            }
        }

        if (!$existingAssoc) {
            return $this->responseError(new XenForo_Phrase(
                'bdapi_consumer_auto_login_with_x_failed',
                array('provider' => $provider['name'])
            ));
        }
        $user = $userModel->getFullUserById($existingAssoc['user_id']);
        if (empty($user)) {
            return $this->responseError(new XenForo_Phrase('requested_user_not_found'));
        }

        if (XenForo_Application::$versionId > 1050000) {
            /** @var XenForo_ControllerHelper_Login $loginHelper */
            $loginHelper = $this->getHelper('Login');

            if ($loginHelper->userTfaConfirmationRequired($user)) {
                $loginHelper->setTfaSessionCheck($user['user_id']);

                return $this->responseMessage(new XenForo_Phrase('bdapi_consumer_auto_login_user_x_requires_tfa', array(
                    'username' => $user['username'],
                    'twoStepLink' => XenForo_Link::buildPublicLink('login/two-step', null, array(
                        'redirect' => $this->getDynamicRedirect(),
                        'remember' => 1,
                    ))
                )));
            }
        }

        $userModel->setUserRememberCookie($user['user_id']);
        XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'login_api_consumer');
        $userModel->deleteSessionActivity(0, $this->_request->getClientIp(false));

        if (XenForo_Application::$versionId < 1050000) {
            XenForo_Application::getSession()->changeUserId($user['user_id']);
            XenForo_Visitor::setup($user['user_id']);
        } else {
            $visitor = XenForo_Visitor::setup($user['user_id']);
            XenForo_Application::getSession()->userLogin($user['user_id'], $visitor['password_date']);
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            $this->getDynamicRedirect(),
            new XenForo_Phrase('bdapi_consumer_auto_login_with_x_succeeded_y', array(
                'provider' => $provider['name'],
                'username' => $user['username']
            ))
        );
    }
}
