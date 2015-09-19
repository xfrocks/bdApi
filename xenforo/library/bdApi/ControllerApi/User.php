<?php

class bdApi_ControllerApi_User extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if (!empty($userId)) {
            return $this->responseReroute(__CLASS__, 'single');
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

    public function actionSingle()
    {
        $user = $this->_getUserOrError();

        $data = array(
            'user' => $this->_filterDataSingle($this->_getUserModel()->prepareApiDataForUser($user)),
            '_user' => $user,
        );

        return $this->responseData('bdApi_ViewApi_User_Single', $data);
    }

    public function actionGetFind()
    {
        $users = array();
        $username = $this->_input->filterSingle('username', XenForo_Input::STRING);
        $email = $this->_input->filterSingle('user_email', XenForo_Input::STRING);
        if (empty($email)) {
            // backward compatibility
            $email = $this->_input->filterSingle('email', XenForo_Input::STRING);
        }

        if (XenForo_Helper_Email::isEmailValid($email)) {
            $visitor = XenForo_Visitor::getInstance();
            $session = bdApi_Data_Helper_Core::safeGetSession();
            if ($visitor->hasAdminPermission('user')
                && $session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM)
            ) {
                // perform email search only if visitor is an admin and granted admincp scope
                $user = $this->_getUserModel()->getUserByEmail($email);
                if (!empty($user)) {
                    $users[$user['user_id']] = $user;
                }
            }
        }

        if (empty($users) && utf8_strlen($username) >= 2) {
            // perform username search only if nothing found and username is long enough
            $users = $this->_getUserModel()->getUsers(
                array('username' => array($username, 'r')),
                array('limit' => 10)
            );
        }

        $data = array(
            'users' => $this->_filterDataMany($this->_getUserModel()->prepareApiDataForUsers($users)),
        );

        return $this->responseData('bdApi_ViewData_User_Find', $data);
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
            'user_email' => XenForo_Input::STRING,
            'username' => XenForo_Input::STRING,
            'password' => XenForo_Input::STRING,
            'password_algo' => XenForo_Input::STRING,
            'user_dob_day' => XenForo_Input::UINT,
            'user_dob_month' => XenForo_Input::UINT,
            'user_dob_year' => XenForo_Input::UINT,
        ));

        if (empty($input['user_email'])) {
            // backward compatibility
            $input['user_email'] = $this->_input->filterSingle('email', XenForo_Input::STRING);
        }

        $extraInput = $this->_input->filter(array(
            'extra_data' => XenForo_Input::STRING,
            'extra_timestamp' => XenForo_Input::UINT,
        ));
        if (!empty($extraInput['extra_data'])) {
            $extraData = bdApi_Crypt::decryptTypeOne($extraInput['extra_data'], $extraInput['extra_timestamp']);
            if (!empty($extraData)) {
                $extraData = @unserialize($extraData);
            }
            if (empty($extraData)) {
                $extraData = array();
            }
        }

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
        $writer->set('email', $input['user_email']);
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

        if ($options->get('gravatarEnable') && XenForo_Model_Avatar::gravatarExists($input['user_email'])) {
            $writer->set('gravatar', $input['user_email']);
        }

        $writer->set('dob_day', $input['user_dob_day']);
        $writer->set('dob_month', $input['user_dob_month']);
        $writer->set('dob_year', $input['user_dob_year']);

        $writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
        $writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));

        $allowEmailConfirm = true;
        if (!empty($extraData['user_email']) && $extraData['user_email'] == $writer->get('email')) {
            // the email address has been validated by some other mean (external provider?)
            // do not require email confirmation again to avoid complication
            $allowEmailConfirm = false;
        }
        $writer->advanceRegistrationUserState($allowEmailConfirm);

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

        if (!empty($extraData['external_provider']) && !empty($extraData['external_provider_key'])) {
            /* @var $userExternalModel XenForo_Model_UserExternal */
            $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
            $userExternalModel->updateExternalAuthAssociation($extraData['external_provider'], $extraData['external_provider_key'], $user['user_id']);
        }

        if (XenForo_Visitor::getUserId() == 0) {
            XenForo_Visitor::setup($user['user_id']);
        }

        $scopes = $oauth2Model->getSystemSupportedScopes();
        $scopes = bdApi_Template_Helper_Core::getInstance()->scopeJoin($scopes);
        $token = $oauth2Model->getServer()->createAccessToken($clientId, $user['user_id'], $scopes);

        $user = $userModel->getUserById($user['user_id'], $userModel->getFetchOptionsToPrepareApiData());
        $data = array(
            'user' => $this->_filterDataSingle($this->_getUserModel()->prepareApiDataForUser($user)),
            '_user' => $user,
            'token' => $token,
        );

        return $this->responseData('bdApi_ViewApi_User_Single', $data);
    }

    public function actionPutIndex()
    {
        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();

        $input = $this->_input->filter(array(
            'password_old' => XenForo_Input::STRING,
            'password_algo' => XenForo_Input::STRING,

            'user_email' => XenForo_Input::STRING,
            'username' => XenForo_Input::STRING,
            'password' => XenForo_Input::STRING,
            'user_dob_day' => XenForo_Input::UINT,
            'user_dob_month' => XenForo_Input::UINT,
            'user_dob_year' => XenForo_Input::UINT,
        ));

        $session = bdApi_Data_Helper_Core::safeGetSession();
        $isAdmin = $session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM) && $visitor->hasAdminPermission('user');

        $isAuth = false;
        if ($isAdmin && $visitor['user_id'] != $user['user_id']) {
            $isAuth = true;
        } elseif (!empty($input['password_old'])) {
            $auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($user['user_id']);
            if (!empty($auth)) {
                $passwordOld = bdApi_Crypt::decrypt($input['password_old'], $input['password_algo']);
                if ($auth->hasPassword() && $auth->authenticate($user['user_id'], $passwordOld)) {
                    $isAuth = true;
                }
            }
        }
        if (!$isAuth) {
            return $this->responseNoPermission();
        }

        /* @var $writer XenForo_DataWriter_User */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $writer->setExistingData($user, true);

        if (!empty($input['user_email'])) {
            $writer->set('email', $input['user_email']);

            if ($writer->isChanged('email')
                && XenForo_Application::getOptions()->get('registrationSetup', 'emailConfirmation')
                && !$isAdmin
            ) {
                switch ($writer->get('user_state')) {
                    case 'moderated':
                    case 'email_confirm':
                        $writer->set('user_state', 'email_confirm');
                        break;

                    default:
                        $writer->set('user_state', 'email_confirm_edit');
                }
            }
        }

        if (!empty($input['username'])) {
            if (!$isAdmin) {
                return $this->responseNoPermission();
            }

            $writer->set('username', $input['username']);
        }

        if (!empty($input['password'])) {
            $password = bdApi_Crypt::decrypt($input['password'], $input['password_algo']);
            $writer->setPassword($password, $password);
        }

        if (!empty($input['user_dob_day']) && !empty($input['user_dob_month']) && !empty($input['user_dob_year'])) {
            $hasExistingDob = false;
            $hasExistingDob = $hasExistingDob || !!$writer->getExisting('dob_day');
            $hasExistingDob = $hasExistingDob || !!$writer->getExisting('dob_month');
            $hasExistingDob = $hasExistingDob || !!$writer->getExisting('dob_year');

            if ($hasExistingDob) {
                if (!$isAdmin) {
                    // changing dob requires admin permission
                    return $this->responseNoPermission();
                }
            } else {
                // new dob just needs auth
            }

            $writer->set('dob_day', $input['user_dob_day']);
            $writer->set('dob_month', $input['user_dob_month']);
            $writer->set('dob_year', $input['user_dob_year']);
        }

        if (!$writer->hasChanges()) {
            return $this->responseError(new XenForo_Phrase('error_occurred_or_request_stopped'), 400);
        }

        $writer->save();

        $user = $writer->getMergedData();
        if ($writer->isChanged('email')
            && in_array($user['user_state'], array('email_confirm', 'email_confirm_edit'))
        ) {
            /* @var $userConfirmationModel XenForo_Model_UserConfirmation */
            $userConfirmationModel = $this->getModelFromCache('XenForo_Model_UserConfirmation');
            $userConfirmationModel->sendEmailConfirmation($user);
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostPassword()
    {
        $link = bdApi_Data_Helper_Core::safeBuildApiLink('users', array('user_id' => $this->_input->filterSingle('user_id', XenForo_Input::UINT)));
        $this->_setDeprecatedHeaders('PUT', $link);

        return $this->responseReroute(__CLASS__, 'put-index');
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

        if ($this->_input->inRequest('total')) {
            $total = $this->_getUserModel()->countUsersFollowingUserId($user['user_id']);
            $data = array('users_total' => $total);
            return $this->responseData('bdApi_ViewApi_User_Followers_Total', $data);
        }

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

        if ($this->_input->inRequest('total')) {
            $total = $this->_getUserModel()->bdApi_countUsersBeingFollowedByUserId($user['user_id']);
            $data = array('users_total' => $total);
            return $this->responseData('bdApi_ViewApi_User_Followings_Total', $data);
        }

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

    public function actionGetIgnored()
    {
        $this->_assertRegistrationRequired();

        if ($this->_input->inRequest('total')) {
            $total = $this->_getIgnoreModel()->bdApi_countIgnoredUsers(XenForo_Visitor::getUserId());
            $data = array('users_total' => $total);
            return $this->responseData('bdApi_ViewApi_User_Ignored_Total', $data);
        }

        $ignoredUsers = $this->_getIgnoreModel()->getIgnoredUsers(XenForo_Visitor::getUserId());

        $data = array('users' => array());

        foreach ($ignoredUsers as $ignoredUser) {
            $data['users'][] = array(
                'user_id' => $ignoredUser['user_id'],
                'username' => $ignoredUser['username'],
            );
        }

        return $this->responseData('bdApi_ViewApi_User_Ignored', $data);
    }

    public function actionPostIgnore()
    {
        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();

        if (!$this->_getIgnoreModel()->canIgnoreUser($visitor['user_id'], $user, $error)) {
            return $this->responseError($error);
        }

        $this->_getIgnoreModel()->ignoreUsers($visitor['user_id'], $user['user_id']);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionDeleteIgnore()
    {
        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();

        if (!$this->_getIgnoreModel()->canIgnoreUser($visitor['user_id'], $user, $error)) {
            return $this->responseError($error);
        }

        $this->_getIgnoreModel()->unignoreUser($visitor['user_id'], $user['user_id']);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetGroups()
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if (!empty($userId)) {
            $user = $this->_getUserOrError();

            if ($user['user_id'] != XenForo_Visitor::getUserId()) {
                // viewing groups of other user requires admin permission
                $this->_assertAdminPermission('user');
            }

            $user = $this->_getUserModel()->prepareApiDataForUser($user);
            $userGroups = $user['user_groups'];
        } else {
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

    public function actionGetTimeline()
    {
        $user = $this->_getUserOrError();

        /** @var XenForo_Model_UserProfile $userProfileModel */
        $userProfileModel = $this->getModelFromCache('XenForo_Model_UserProfile');
        if (!$userProfileModel->canViewProfilePosts($user)) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('user_id', $user['user_id']);
        return $this->responseReroute('bdApi_ControllerApi_Search', 'user-timeline');
    }

    public function actionPostTimeline()
    {
        return $this->responseReroute('bdApi_ControllerApi_ProfilePost', 'post-index');
    }

    public function actionGetMe()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionPutMe()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'put-index');
    }

    public function actionPostMeAvatar()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'post-avatar');
    }

    public function actionDeleteMeAvatar()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'delete-avatar');
    }

    public function actionGetMeFollowers()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-followers');
    }

    public function actionGetMeFollowings()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-followings');
    }

    public function actionPostMePassword()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'post-password');
    }

    public function actionGetMeGroups()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-groups');
    }

    public function actionPostMeGroups()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'post-groups');
    }

    public function actionGetMeTimeline()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'get-timeline');
    }

    public function actionPostMeTimeline()
    {
        $this->_assertRegistrationRequired();

        $this->_request->setParam('user_id', XenForo_Visitor::getUserId());
        return $this->responseReroute(__CLASS__, 'post-timeline');
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

    /**
     * @return bdApi_XenForo_Model_UserIgnore
     */
    protected function _getIgnoreModel()
    {
        return $this->getModelFromCache('XenForo_Model_UserIgnore');
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

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $params['user_id'] = $this->_request->getParam('user_id');
        $controllerName = 'XenForo_ControllerPublic_Member';
    }
}
