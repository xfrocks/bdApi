<?php

class bdApiConsumer_XenForo_ControllerPublic_Account extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Account
{
    public function actionSecurity()
    {
        $response = parent::actionSecurity();

        if (bdApiConsumer_Option::get('takeOver', 'login')) {
            if ($response instanceof XenForo_ControllerResponse_View
                && !empty($response->subView)
                && empty($response->subView->params['hasPassword'])
            ) {
                /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
                $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
                $auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations(XenForo_Visitor::getUserId());

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

}
