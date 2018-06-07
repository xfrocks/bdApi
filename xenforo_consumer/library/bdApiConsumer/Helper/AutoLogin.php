<?php

class bdApiConsumer_Helper_AutoLogin
{
    public static function updateResponseRedirect(XenForo_Controller $controller, XenForo_ControllerResponse_Redirect $controllerResponse)
    {
        $action = false;
        $userId = 0;
        if ($controller instanceof XenForo_ControllerPublic_Login) {
            /** @var bdApiConsumer_XenForo_ControllerPublic_Login $controller */
            if (XenForo_Visitor::getUserId() > 0
                && XenForo_Visitor::getUserId() != $controller->bdApiConsumer_getBeforeLoginVisitorId()
            ) {
                // a successful login
                $action = 'login';
                $userId = XenForo_Visitor::getUserId();
            }
        } elseif ($controller instanceof XenForo_ControllerPublic_Logout) {
            /** @var bdApiConsumer_XenForo_ControllerPublic_Logout $controller */
            if (XenForo_Visitor::getUserId() == 0) {
                // a successful logout
                $action = 'logout';
                $userId = $controller->bdApiConsumer_getBeforeLogoutVisitorId();
            }
        }

        if ($action !== false
            && $userId > 0
        ) {
            $redirectTarget = $controllerResponse->redirectTarget;
            $originalTarget = $redirectTarget;

            /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
            $userExternalModel = $controller->getModelFromCache('XenForo_Model_UserExternal');
            $auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations($userId);

            if (!empty($auths)) {
                foreach ($auths as $auth) {
                    $provider = bdApiConsumer_Option::getProviderByCode($auth['provider']);
                    if (empty($provider)) {
                        continue;
                    }

                    $accessToken = $userExternalModel->bdApiConsumer_getAccessTokenFromAuth($provider, $auth);
                    if (empty($accessToken)) {
                        continue;
                    }

                    $ott = bdApiConsumer_Helper_Api::generateOneTimeToken($provider, $auth['provider_key'], $accessToken);

                    $redirectTarget = XenForo_Link::convertUriToAbsoluteUri($redirectTarget, true);

                    switch ($action) {
                        case 'login':
                            $redirectTarget = bdApiConsumer_Helper_Api::getLoginLink($provider, $ott, $redirectTarget);
                            break;
                        case 'logout':
                            $redirectTarget = bdApiConsumer_Helper_Api::getLogoutLink($provider, $ott, $redirectTarget);
                            break;
                    }
                }
            }

            if ($redirectTarget !== $originalTarget) {
                $controllerResponse->redirectTarget = $redirectTarget;
            }
        }
    }
}
