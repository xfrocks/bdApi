<?php

class bdApiConsumer_XenForo_Model_UserConfirmation extends XFCP_bdApiConsumer_XenForo_Model_UserConfirmation
{
    public function sendPasswordResetRequest(array $user, array $confirmation = null)
    {
        if (empty($confirmation) AND $this->_bdApiConsumer_tryExternalPasswordResetRequest($user)) {
            return true;
        }

        return parent::sendPasswordResetRequest($user, $confirmation);
    }

    protected function _bdApiConsumer_tryExternalPasswordResetRequest(array $user)
    {
        if (!bdApiConsumer_Option::get('takeOver', 'login')) {
            return false;
        }

        /** @var XenForo_Model_User $userModel */
        $userModel = $this->getModelFromCache('XenForo_Model_User');
        $authentication = $userModel->getUserAuthenticationObjectByUserId($user['user_id']);
        if ($authentication->hasPassword()) {
            return false;
        }

        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
        $auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations($user['user_id']);
        if (empty($auths)) {
            return false;
        }

        foreach ($auths as $auth) {
            $provider = bdApiConsumer_Option::getProviderByCode($auth['provider']);
            if (empty($provider)) {
                continue;
            }

            $accessToken = $userExternalModel->bdApiConsumer_getAccessTokenFromAuth($provider, $auth);
            if (empty($accessToken)) {
                continue;
            }

            bdApiConsumer_Helper_Api::postPasswordResetRequest($provider, $accessToken);
        }

        return true;
    }
}
