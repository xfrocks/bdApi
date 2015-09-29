<?php

class bdApi_XenForo_ControllerPublic_Login extends XFCP_bdApi_XenForo_ControllerPublic_Login
{
    public function actionApi()
    {
        $input = $this->_input->filter(array(
            'redirect' => XenForo_Input::STRING,
            'timestamp' => XenForo_Input::UINT,
            'user_id' => XenForo_Input::STRING,
        ));

        $userId = 0;
        if (!empty($input['user_id'])
            && !empty($input['timestamp'])
        ) {
            try {
                $userId = intval(bdApi_Crypt::decryptTypeOne($input['user_id'], $input['timestamp']));
            } catch (XenForo_Exception $e) {
                if (XenForo_Application::debugMode()) {
                    $this->_response->setHeader('X-Api-Exception', $e->getMessage());
                }
            }
        }

        if ($userId > 0) {
            $this->_response->setHeader('X-Api-Login-User', $userId);

            $this->_getUserModel()->setUserRememberCookie($userId);

            XenForo_Model_Ip::log($userId, 'user', $userId, 'login_api');

            $this->_getUserModel()->deleteSessionActivity(0, $this->_request->getClientIp(false));
            $session = XenForo_Application::get('session');
            $session->changeUserId($userId);
            XenForo_Visitor::setup($userId);
        }

        if (empty($input['redirect'])) {
            $input['redirect'] = $this->getDynamicRedirectIfNot(XenForo_Link::buildPublicLink('login'));
        }
        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $input['redirect']);
    }

}
