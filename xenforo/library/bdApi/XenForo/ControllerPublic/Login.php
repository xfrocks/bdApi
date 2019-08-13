<?php

class bdApi_XenForo_ControllerPublic_Login extends XFCP_bdApi_XenForo_ControllerPublic_Login
{
    /**
     * @return XenForo_ControllerResponse_Redirect
     * @throws XenForo_Exception
     *
     * @see XenForo_ControllerPublic_Login::completeLogin()
     */
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
                XenForo_Error::logError($e, false);
            }
        }

        if ($userId > 0) {
            $this->_response->setHeader('X-Api-Login-User', $userId);

            XenForo_Model_Ip::log($userId, 'user', $userId, 'login_api');

            $visitor = XenForo_Visitor::setup($userId);

            XenForo_Application::getSession()->userLogin($userId, $visitor['password_date']);
        }

        if (empty($input['redirect'])) {
            $input['redirect'] = $this->getDynamicRedirectIfNot(XenForo_Link::buildPublicLink('login'));
        }
        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $input['redirect']);
    }
}
