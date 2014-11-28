<?php

class bdApi_ControllerApi_User extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if (!empty($userId)) {
            return $this->responseReroute(__CLASS__, 'get-single');
        }

        $userModel = $this->_getUserModel();

        $pageNavParams = array();
        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $limit = XenForo_Application::get('options')->membersPerPage;

        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

        $conditions = array(
            'user_state' => 'valid',
            'is_banned' => 0
        );
        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page,
            'order' => bdApi_XenForo_Model_User::ORDER_USER_ID,
        );

        $users = $userModel->getUsers($conditions, $userModel->getFetchOptionsToPrepareApiData($fetchOptions));

        $total = $userModel->countUsers($conditions);

        $data = array(
            'users' => $this->_filterDataMany($userModel->prepareApiDataForUsers($users)),
            'users_total' => $total,
        );

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'users', array(), $pageNavParams);

        return $this->responseData('bdApi_ViewApi_User_List', $data);
    }

    public function actionGetSingle()
    {
        $user = $this->_getUserOrError();

        $data = array(
            'user' => $this->_filterDataSingle($this->_getUserModel()->prepareApiDataForUser($user)),
            '_user' => $user,
        );

        return $this->responseData('bdApi_ViewApi_User_Single', $data);
    }

    public function actionPostIndex()
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');
        /* @var $userConfirmationModel XenForo_Model_UserConfirmation */
        $userConfirmationModel = $this->getModelFromCache('XenForo_Model_UserConfirmation');
        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();

        $clientId = $session->getOAuthClientId();
        $clientSecret = $session->getOAuthClientSecret();
        if (empty($clientId) OR empty($clientSecret)) {
            $clientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
            $client = $oauth2Model->getClientModel()->getClientById($clientId);
            if (empty($client)) {
                return $this->responseError(new XenForo_Phrase('bdapi_post_slash_users_requires_client_id'), 400);
            }
            $clientSecret = $client['client_secret'];
        }

        $input = $this->_input->filter(array(
            'email' => XenForo_Input::STRING,
            'username' => XenForo_Input::STRING,
            'password' => XenForo_Input::STRING,
            'password_algo' => XenForo_Input::STRING,
            'user_dob_day' => XenForo_Input::UINT,
            'user_dob_month' => XenForo_Input::UINT,
            'user_dob_year' => XenForo_Input::UINT,
        ));
        $userModel = $this->_getUserModel();
        $options = XenForo_Application::getOptions();
        $session = XenForo_Application::getSession();
        $visitor = XenForo_Visitor::getInstance();

        /* @var $writer XenForo_DataWriter_User */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $registrationDefaults = $options->get('registrationDefaults');
        if (!empty($registrationDefaults)) {
            $writer->bulkSet($registrationDefaults, array('ignoreInvalidFields' => true));
        }
        $writer->set('email', $input['email']);
        $writer->set('username', $input['username']);

        $password = bdApi_Crypt::decrypt($input['password'], $input['password_algo'], $clientSecret);
        if (!empty($password)) {
            $writer->setPassword($password, $password);
        } else {
            // no password or unable to decrypt password
            // create new user with no password auth scheme
            $auth = XenForo_Authentication_Abstract::create('XenForo_Authentication_NoPassword');
            $writer->set('scheme_class', $auth->getClassName());
            $writer->set('data', $auth->generate(''), 'xf_user_authenticate');
        }

        if ($options->get('gravatarEnable') && XenForo_Model_Avatar::gravatarExists($input['email'])) {
            $writer->set('gravatar', $input['email']);
        }

        $writer->set('dob_day', $input['user_dob_day']);
        $writer->set('dob_month', $input['user_dob_month']);
        $writer->set('dob_year', $input['user_dob_year']);

        $writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
        $writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));

        $writer->advanceRegistrationUserState();

        if ($visitor->hasAdminPermission('user') AND $session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM)) {
            $writer->set('user_state', 'valid');
        }

        $writer->save();

        $user = $writer->getMergedData();

        // log the ip of the user registering
        XenForo_Model_Ip::log(XenForo_Visitor::getUserId() ? XenForo_Visitor::getUserId() : $user['user_id'], 'user', $user['user_id'], 'register');

        if ($user['user_state'] == 'email_confirm') {
            $userConfirmationModel->sendEmailConfirmation($user);
        }

        if (XenForo_Visitor::getUserId() == 0) {
            XenForo_Visitor::setup($user['user_id']);
        }

        $oauth2Server = $oauth2Model->getServer();
        $oauth2ServerUserId = $oauth2Server->getUserId();

        $scopes = $oauth2Model->getSystemSupportedScopes();
        $scopes = bdApi_Template_Helper_Core::getInstance()->scopeJoin($scopes);

        $oauth2Server->setUserId($user['user_id']);
        $token = $oauth2Server->createAccessTokenPublic($clientId, $scopes);
        $oauth2Server->setUserId($oauth2ServerUserId);

        $user = $userModel->getUserById($user['user_id'], $userModel->getFetchOptionsToPrepareApiData());
        $data = array(
            'user' => $this->_filterDataSingle($this->_getUserModel()->prepareApiDataForUser($user)),
            '_user' => $user,
            'token' => $token,
        );

        return $this->responseData('bdApi_ViewApi_User_Single', $data);
    }

    public function actionPostAvatar()
    {
        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();

        if ($user['user_id'] != $visitor->get('user_id')) {
            return $this->responseNoPermission();
        }

        if (!$visitor->canUploadAvatar()) {
            return $this->responseNoPermission();
        }

        $avatar = XenForo_Upload::getUploadedFile('avatar');
        if (empty($avatar)) {
            return $this->responseError(new XenForo_Phrase('bdapi_requires_upload_x', array('field' => 'avatar')), 400);
        }

        /* @var $avatarModel XenForo_Model_Avatar */
        $avatarModel = $this->getModelFromCache('XenForo_Model_Avatar');
        $avatarModel->uploadAvatar($avatar, $visitor->get('user_id'), $visitor->getPermissions());

        return $this->responseMessage(new XenForo_Phrase('upload_completed_successfully'));
    }

    public function actionDeleteAvatar()
    {
        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();
        /* @var $avatarModel XenForo_Model_Avatar */
        $avatarModel = $this->getModelFromCache('XenForo_Model_Avatar');

        if ($user['user_id'] != $visitor->get('user_id')) {
            return $this->responseNoPermission();
        }

        if (!$visitor->canUploadAvatar()) {
            return $this->responseNoPermission();
        }

        $avatarModel->deleteAvatar($visitor->get('user_id'));

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetFollowers()
    {
        $user = $this->_getUserOrError();

        $followers = $this->_getUserModel()->getUsersFollowingUserId($user['user_id'], 0, 'user.user_id');

        $data = array('users' => array());

        foreach ($followers as $follower) {
            $data['users'][] = array(
                'user_id' => $follower['user_id'],
                'username' => $follower['username'],
            );
        }

        return $this->responseData('bdApi_ViewApi_User_Followers', $data);
    }

    public function actionPostFollowers()
    {
        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();

        if (($user['user_id'] == $visitor->get('user_id')) OR !$visitor->canFollow()) {
            return $this->responseNoPermission();
        }

        $this->_getUserModel()->follow($user);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionDeleteFollowers()
    {
        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();

        if (($user['user_id'] == $visitor->get('user_id')) OR !$visitor->canFollow()) {
            return $this->responseNoPermission();
        }

        $this->_getUserModel()->unfollow($user['user_id']);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetFollowings()
    {
        $user = $this->_getUserOrError();

        $followings = $this->_getUserModel()->getFollowedUserProfiles($user['user_id'], 0, 'user.user_id');

        $data = array('users' => array());

        foreach ($followings as $following) {
            $data['users'][] = array(
                'user_id' => $following['user_id'],
                'username' => $following['username'],
            );
        }

        return $this->responseData('bdApi_ViewApi_User_Followings', $data);
    }

    public function actionPostPassword()
    {
        $input = $this->_input->filter(array(
            'password_old' => XenForo_Input::STRING,
            'password' => XenForo_Input::STRING,
            'password_algo' => XenForo_Input::STRING,
        ));

        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();
        $passwordOld = bdApi_Crypt::decrypt($input['password_old'], $input['password_algo']);
        $password = bdApi_Crypt::decrypt($input['password'], $input['password_algo']);

        $session = bdApi_Data_Helper_Core::safeGetSession();
        if ($session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM)
            && $visitor->hasAdminPermission('user')
            && $visitor['user_id'] != $user['user_id']
        ) {
            // current user has admin permission, bypass old password verification
            // do not bypass if changing self password though
        } else {
            $auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($user['user_id']);
            if (empty($auth)) {
                return $this->responseNoPermission();
            }
            if ($auth->hasPassword() && !$auth->authenticate($user['user_id'], $passwordOld)) {
                return $this->responseNoPermission();
            }
        }

        /* @var $writer XenForo_DataWriter_User */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $writer->setExistingData($user, true);
        $writer->setPassword($password, $password);
        $writer->save();

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostPasswordTest()
    {
        $input = $this->_input->filter(array(
            'password' => XenForo_Input::STRING,
            'password_algo' => XenForo_Input::STRING,
            'decrypt' => XenForo_Input::UINT,
        ));

        if (!XenForo_Application::debugMode()) {
            return $this->responseNoPermission();
        }

        if (empty($input['decrypt'])) {
            $result = bdApi_Crypt::encrypt($input['password'], $input['password_algo']);
        } else {
            $result = bdApi_Crypt::decrypt($input['password'], $input['password_algo']);
        }

        $data = array('result' => $result);

        return $this->responseData('bdApi_ViewApi_User_PasswordTest', $data);
    }

    public function actionGetGroups()
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if (!empty($userId)) {
            $user = $this->_getUserOrError();

            if ($user['user_id'] != XenForo_Visitor::getUserId()) {
                // viewing groups of other user requires admin permission
                $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM);
                $this->_assertAdminPermission('user');
            }

            $user = $this->_getUserModel()->prepareApiDataForUser($user);
            $userGroups = $user['user_groups'];
        } else {
            $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM);
            $this->_assertAdminPermission('user');

            $userGroupModel = $this->_getUserGroupModel();
            $userGroups = $userGroupModel->getAllUserGroups();
            $userGroups = $userGroupModel->prepareApiDataForUserGroups($userGroups);
        }

        $data = array('user_groups' => $this->_filterDataMany($userGroups));

        if (!empty($user)) {
            $data['user_id'] = $user['user_id'];
        }

        return $this->responseData('bdApi_ViewApi_User_Groups', $data);
    }

    public function actionPostGroups()
    {
        $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM);
        $this->_assertAdminPermission('user');

        $user = $this->_getUserOrError();

        $primaryGroupId = $this->_input->filterSingle('primary_group_id', XenForo_Input::UINT);
        $secondaryGroupIds = $this->_input->filterSingle('secondary_group_ids', XenForo_Input::UINT, array('array' => true));

        $userGroups = $this->_getUserGroupModel()->getAllUserGroups();
        if (!isset($userGroups[$primaryGroupId])) {
            return $this->responseError(new XenForo_Phrase('requested_user_group_not_found'));
        }
        if (!empty($secondaryGroupIds)) {
            foreach ($secondaryGroupIds as $secondaryGroupId) {
                if (!isset($userGroups[$secondaryGroupId])) {
                    return $this->responseError(new XenForo_Phrase('requested_user_group_not_found'));
                }
            }
        }

        /* @var $writer XenForo_DataWriter_User */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $writer->setExistingData($user, true);
        $writer->set('user_group_id', $primaryGroupId);
        $writer->setSecondaryGroups($secondaryGroupIds);
        $writer->save();

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetMe()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-single');
    }

    public function actionPostMeAvatar()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'post-avatar');
    }

    public function actionDeleteMeAvatar()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'delete-avatar');
    }

    public function actionGetMeFollowers()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-followers');
    }

    public function actionGetMeFollowings()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-followings');
    }

    public function actionPostMePassword()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'post-password');
    }

    public function actionGetMeGroups()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-groups');
    }

    public function actionPostMeGroups()
    {
        if (XenForo_Visitor::getUserId() == 0) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'post-groups');
    }

    /**
     * @return bdApi_XenForo_Model_User
     */
    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }

    /**
     * @return bdApi_XenForo_Model_UserGroup
     */
    protected function _getUserGroupModel()
    {
        return $this->getModelFromCache('XenForo_Model_UserGroup');
    }

    protected function _getUserOrError(array $fetchOptions = array())
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

        $userModel = $this->_getUserModel();

        $user = $userModel->getUserById($userId, $userModel->getFetchOptionsToPrepareApiData($fetchOptions));

        if (empty($user)) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('requested_user_not_found'), 404));
        }

        return $user;
    }

    protected function _getScopeForAction($action)
    {
        if ($action === 'PostIndex') {
            /* @var $session bdApi_Session */

            $session = XenForo_Application::getSession();
            $clientId = $session->getOAuthClientId();

            if (empty($clientId)) {
                return false;
            }
        }

        return parent::_getScopeForAction($action);
    }

}
