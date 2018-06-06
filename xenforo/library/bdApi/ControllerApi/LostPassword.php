<?php

class bdApi_ControllerApi_LostPassword extends bdApi_ControllerApi_Abstract
{
    public function actionPostIndex()
    {
        $username = $this->_input->filterSingle('username', XenForo_Input::STRING);
        $email = $this->_input->filterSingle('email', XenForo_Input::STRING);
        $usernameOrEmail = $username ?: $email;
        if (empty($usernameOrEmail)) {
            return $this->responseError(
                new XenForo_Phrase('bdapi_slash_lost_password_requires_username_or_email'),
                400
            );
        }

        if (XenForo_Visitor::getUserId() && !XenForo_Application::debugMode()) {
            return $this->responseNoPermission();
        }

        $session = bdApi_Data_Helper_Core::safeGetSession();
        $clientId = $session->getOAuthClientId();
        if (empty($clientId)) {
            return $this->responseNoPermission();
        }

        $user = $this->_getUserModel()->getUserByNameOrEmail($usernameOrEmail);
        if (!$user) {
            return $this->responseError(new XenForo_Phrase('requested_member_not_found'));
        }

        $confirmationModel = $this->_getUserConfirmationModel();
        $lostPasswordTimeLimit = XenForo_Application::getOptions()->lostPasswordTimeLimit;
        if ($lostPasswordTimeLimit > 0) {
            if ($confirmation = $confirmationModel->getUserConfirmationRecord($user['user_id'], 'password')) {
                $timeDiff = XenForo_Application::$time - $confirmation['confirmation_date'];

                if ($lostPasswordTimeLimit > $timeDiff) {
                    return $this->responseFlooding($lostPasswordTimeLimit - $timeDiff);
                }
            }
        }

        $confirmationModel->sendPasswordResetRequest($user);

        return $this->responseMessage(new XenForo_Phrase('password_reset_request_has_been_emailed_to_you'));
    }

    /**
     * @return XenForo_Model_UserConfirmation
     */
    protected function _getUserConfirmationModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_UserConfirmation');
    }


    protected function _getScopeForAction($action)
    {
        return false;
    }

    /**
     * @return XenForo_Model_User
     */
    protected function _getUserModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_User');
    }
}
