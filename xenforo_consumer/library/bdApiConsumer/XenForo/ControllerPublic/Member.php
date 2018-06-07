<?php

class bdApiConsumer_XenForo_ControllerPublic_Member extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Member
{
    public function actionMember()
    {
        $response = parent::actionMember();

        if (bdApiConsumer_Option::get('takeOver', 'profile')) {
            if ($response instanceof XenForo_ControllerResponse_View AND !empty($response->params['user'])) {
                /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
                $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
                $auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations(
                    $response->params['user']['user_id']
                );

                foreach ($auths as $auth) {
                    if (!empty($auth['extra_data']['links']['permalink'])) {
                        return $this->responseRedirect(
                            XenForo_ControllerResponse_Redirect::SUCCESS,
                            $auth['extra_data']['links']['permalink']
                        );
                    }
                }
            }
        }

        return $response;
    }

    public function actionExternalAvatar()
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

        /** @var bdApiConsumer_XenForo_Model_UserExternal $userExternalModel */
        $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
        $auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations($userId);
        foreach ($auths as $auth) {
            $avatarUrl = bdApiConsumer_Helper_Avatar::getAvatarUrlFromAuthExtra($auth['extra_data']);
            if (!empty($avatarUrl)) {
                return $this->responseRedirect(XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL, $avatarUrl);
            }
        }

        return $this->responseNoPermission();
    }
}
