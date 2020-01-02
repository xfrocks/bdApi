<?php

class bdApi_ControllerApi_User extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if (!empty($userId)) {
            return $this->responseReroute(__CLASS__, 'single');
        }

        $userIds = $this->_input->filterSingle('user_ids', XenForo_Input::STRING);
        if (!empty($userIds)) {
            return $this->responseReroute(__CLASS__, 'multiple');
        }

        $pageNavParams = array();
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $userModel = $this->_getUserModel();
        if (!$userModel->canViewMemberList()) {
            if ($limit === 1) {
                // special case to support subscription discovery of topic user_0
                return $this->responseData('bdApi_ViewApi_User_List', array('users' => array()));
            }

            return $this->responseNoPermission();
        }

        $conditions = array(
            'user_state' => 'valid',
            'is_banned' => 0
        );
        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page,
            'order' => bdApi_Extend_Model_User::ORDER_USER_ID,
        );

        // manually prepare users total count for paging
        $total = $userModel->bdApi_getLatestUserId();

        $userIdEnd = max(1, $fetchOptions['page']) * $fetchOptions['limit'];
        $userIdStart = $userIdEnd - $fetchOptions['limit'] + 1;
        $conditions[bdApi_Extend_Model_User::CONDITIONS_USER_ID] =
            array('>=<', $userIdStart, $userIdEnd);

        // paging was done by conditions (see above), remove it from fetch options
        $fetchOptions['page'] = 0;

        $users = $userModel->getUsers($conditions, $userModel->getFetchOptionsToPrepareApiData($fetchOptions));
        $usersData = $this->_prepareUsers($users);

        $data = array(
            'users' => $this->_filterDataMany($usersData),
            'users_total' => $total,
        );

        bdApi_Data_Helper_Core::addPageLinks(
            $this->getInput(),
            $data,
            $limit,
            $total,
            $page,
            'users',
            array(),
            $pageNavParams
        );

        return $this->responseData('bdApi_ViewApi_User_List', $data);
    }

    public function actionSingle()
    {
        $user = $this->_getUserOrError();

        if (!$this->_getUserProfileModel()->canViewFullUserProfile($user, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $users = array($user['user_id'] => $user);
        $usersData = $this->_prepareUsers($users);

        $userData = reset($usersData);
        if (empty($userData)) {
            return $this->responseNoPermission();
        }

        $data = array('user' => $this->_filterDataSingle($userData));

        return $this->responseData('bdApi_ViewApi_User_Single', $data);
    }

    public function actionMultiple()
    {
        $userIdsInput = $this->_input->filterSingle('user_ids', XenForo_Input::STRING);
        $userIds = array_map('intval', explode(',', $userIdsInput));
        if (empty($userIds)) {
            return $this->responseNoPermission();
        }

        $users = $this->_getUserModel()->getUsersByIds(
            $userIds,
            $this->_getUserModel()->getFetchOptionsToPrepareApiData()
        );

        $userProfileModel = $this->_getUserProfileModel();
        $usersOrdered = array();
        foreach ($userIds as $userId) {
            if (!$userProfileModel->canViewFullUserProfile($users[$userId])) {
                continue;
            }

            if (isset($users[$userId])) {
                $usersOrdered[$userId] = $users[$userId];
            }
        }

        $usersData = $this->_prepareUsers($usersOrdered);

        $data = array(
            'users' => $this->_filterDataMany($usersData),
        );

        return $this->responseData('bdApi_ViewApi_User_List', $data);
    }

    public function actionGetFields()
    {
        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if ($userId > 0) {
            return $this->responseNoPermission();
        }

        $userModel = $this->_getUserModel();

        $fakeUser = array('custom_fields' => 'a:0:{}');
        $fields = $userModel->prepareApiDataForUserFields($fakeUser, true);

        $data = array('fields' => $this->_filterDataMany($fields));

        return $this->responseData('bdApi_ViewApi_User_Fields', $data);
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
                $user = $this->_getUserModel()->getUserByEmail(
                    $email,
                    $this->_getUserModel()->getFetchOptionsToPrepareApiData()
                );
                if (!empty($user)) {
                    $users[$user['user_id']] = $user;
                }
            }
        }

        if (empty($users) && utf8_strlen($username) >= 2) {
            // perform username search only if nothing found and username is long enough
            $users = $this->_getUserModel()->getUsers(
                array('username' => array($username, 'r')),
                $this->_getUserModel()->getFetchOptionsToPrepareApiData(
                    array(
                        'limit' => 10,
                    )
                )
            );
        }

        $data = array(
            'users' => $this->_filterDataMany($this->_getUserModel()->prepareApiDataForUsers($users)),
        );

        return $this->responseData('bdApi_ViewData_User_Find', $data);
    }

    public function actionPostIndex()
    {
        if (!XenForo_Application::get('options')->get('registrationSetup', 'enabled')) {
            return $this->responseError(new XenForo_Phrase('new_registrations_currently_not_being_accepted'));
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

        $fieldValues = $this->_input->filterSingle('fields', XenForo_Input::ARRAY_SIMPLE);
        foreach ($userModel->bdApi_getSystemFields() as $systemField) {
            if (empty($fieldValues[$systemField])) {
                continue;
            }

            $writer->set($systemField, $fieldValues[$systemField]);
            unset($fieldValues[$systemField]);
        }
        $writer->setCustomFields($fieldValues);

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
        XenForo_Model_Ip::log(
            XenForo_Visitor::getUserId() ? XenForo_Visitor::getUserId() : $user['user_id'],
            'user',
            $user['user_id'],
            'register'
        );

        if ($user['user_state'] == 'email_confirm') {
            $userConfirmationModel->sendEmailConfirmation($user);
        }

        if (!empty($extraData['external_provider']) && !empty($extraData['external_provider_key'])) {
            /* @var $userExternalModel XenForo_Model_UserExternal */
            $userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
            $userExternalModel->updateExternalAuthAssociation(
                $extraData['external_provider'],
                $extraData['external_provider_key'],
                $user['user_id']
            );
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
        $input = $this->_input->filter(array(
            'password' => XenForo_Input::STRING,
            'password_old' => XenForo_Input::STRING,
            'password_algo' => XenForo_Input::STRING,
            'user_email' => XenForo_Input::STRING,

            'username' => XenForo_Input::STRING,
            'user_title' => XenForo_Input::STRING,
            'primary_group_id' => XenForo_Input::UINT,
            'secondary_group_ids' => array(XenForo_Input::UINT, 'array' => true),

            'user_dob_day' => XenForo_Input::UINT,
            'user_dob_month' => XenForo_Input::UINT,
            'user_dob_year' => XenForo_Input::UINT,

            'fields' => XenForo_Input::ARRAY_SIMPLE,
        ));

        $user = $this->_getUserOrError();
        $visitor = XenForo_Visitor::getInstance();

        $session = bdApi_Data_Helper_Core::safeGetSession();
        $isAdmin = $session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM) && $visitor->hasAdminPermission('user');

        $requiredAuth = 0;
        if (!empty($input['password'])) {
            $requiredAuth++;
        }
        if (!empty($input['user_email'])) {
            $requiredAuth++;
        }
        if ($requiredAuth > 0) {
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
                return $this->responseError(new XenForo_Phrase('bdapi_slash_users_requires_password_old'), 403);
            }
        }

        /* @var $writer XenForo_DataWriter_User */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $writer->setExistingData($user, true);
        if ($isAdmin) {
            $writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);
        }

        if (!empty($input['password'])) {
            $password = bdApi_Crypt::decrypt($input['password'], $input['password_algo']);
            $writer->setPassword($password, $password);
        }

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
            $writer->set('username', $input['username']);

            if ($writer->isChanged('username')
                && !$isAdmin
            ) {
                return $this->responseError(new XenForo_Phrase('bdapi_slash_users_denied_username'), 403);
            }
        }

        if ($this->_input->inRequest('user_title')) {
            $tmpUser = $this->_getUserModel()->prepareApiDataForUser($user);
            if ($input['user_title'] !== $tmpUser['user_title']) {
                $writer->set('custom_title', $input['user_title']);

                if ($writer->isChanged('custom_title')
                    && !$isAdmin
                ) {
                    return $this->responseError(new XenForo_Phrase('bdapi_slash_users_denied_user_title'), 403);
                }
            }
        }

        if ($input['primary_group_id'] > 0) {
            $userGroups = $this->_getUserGroupModel()->getAllUserGroups();
            if (!isset($userGroups[$input['primary_group_id']])) {
                return $this->responseError(new XenForo_Phrase('requested_user_group_not_found'));
            }

            if (!empty($input['secondary_group_ids'])) {
                foreach ($input['secondary_group_ids'] as $secondaryGroupId) {
                    if (!isset($userGroups[$secondaryGroupId])) {
                        return $this->responseError(new XenForo_Phrase('requested_user_group_not_found'));
                    }
                }
            }

            $writer->set('user_group_id', $input['primary_group_id']);
            $writer->setSecondaryGroups($input['secondary_group_ids']);
        }

        if (!empty($input['user_dob_day']) && !empty($input['user_dob_month']) && !empty($input['user_dob_year'])) {
            $writer->set('dob_day', $input['user_dob_day']);
            $writer->set('dob_month', $input['user_dob_month']);
            $writer->set('dob_year', $input['user_dob_year']);

            $hasExistingDob = false;
            $hasExistingDob = $hasExistingDob || !!$writer->getExisting('dob_day');
            $hasExistingDob = $hasExistingDob || !!$writer->getExisting('dob_month');
            $hasExistingDob = $hasExistingDob || !!$writer->getExisting('dob_year');

            if ($hasExistingDob
                && (
                    $writer->isChanged('dob_day')
                    || $writer->isChanged('dob_month')
                    || $writer->isChanged('dob_year')
                )
                && !$isAdmin
            ) {
                // setting new dob is fine but changing dob requires admin permission
                return $this->responseError(new XenForo_Phrase('bdapi_slash_users_denied_dob'), 403);
            }
        }

        if (!empty($input['fields'])) {
            $profileFieldsInput = new XenForo_Input($input['fields']);
            $profileFields = $profileFieldsInput->filter(array(
                'about' => XenForo_Input::STRING,
                'homepage' => XenForo_Input::STRING,
                'location' => XenForo_Input::STRING,
                'occupation' => XenForo_Input::STRING,
            ));
            $writer->bulkSet($profileFields);
            $writer->setCustomFields($input['fields']);
        }

        $writer->preSave();

        if (!$isAdmin) {
            if ($writer->isChanged('user_group_id')
                || $writer->isChanged('secondary_group_ids')
            ) {
                // this has to be checked here because `secondary_group_ids` only get set within preSave()
                return $this->responseError(new XenForo_Phrase('bdapi_slash_users_denied_user_group'), 403);
            }
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
        $link = bdApi_Data_Helper_Core::safeBuildApiLink(
            'users',
            array('user_id' => $this->_input->filterSingle('user_id', XenForo_Input::UINT))
        );
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
        $link = bdApi_Data_Helper_Core::safeBuildApiLink(
            'users',
            array('user_id' => $this->_input->filterSingle('user_id', XenForo_Input::UINT))
        );
        $this->_setDeprecatedHeaders('PUT', $link);

        return $this->responseReroute('bdApi_ControllerApi_User', 'put-index');
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

    protected function _prepareUsers(array $users)
    {
        $usersData = array();

        $userModel = $this->_getUserModel();

        $followersTotals = null;
        if ($this->_isFieldIncluded('user_followers_total')) {
            $followersTotals = $userModel->bdApi_countUsersFollowingUserIds(array_keys($users));
        }

        $includeFollowingsTotal = $this->_isFieldIncluded('user_followings_total');

        foreach ($users as &$userRef) {
            $userData = $userModel->prepareApiDataForUser($userRef);

            if (is_array($followersTotals)) {
                if (!empty($followersTotals[$userRef['user_id']])) {
                    $userData['user_followers_total'] = intval($followersTotals[$userRef['user_id']]);
                } else {
                    $userData['user_followers_total'] = 0;
                }
            }

            if ($includeFollowingsTotal) {
                $userFollowingUserIds = explode(',', isset($userRef['following']) ? $userRef['following'] : '');
                if (empty($userFollowingUserIds)) {
                    $userFollowingUserIds = array();
                }
                $userData['user_followings_total'] = count($userFollowingUserIds);
            }

            $usersData[] = $userData;
        }

        return $usersData;
    }

    /**
     * @return bdApi_Extend_Model_User
     */
    protected function _getUserModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_User');
    }

    /**
     * @return bdApi_Extend_Model_UserGroup
     */
    protected function _getUserGroupModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_UserGroup');
    }

    /**
     * @return XenForo_Model_UserProfile
     */
    protected function _getUserProfileModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_UserProfile');
    }

    /**
     * @return bdApi_Extend_Model_UserIgnore
     */
    protected function _getIgnoreModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
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
            $session = bdApi_Data_Helper_Core::safeGetSession();
            if (!$session || !$session->getOAuthClientId()) {
                return false;
            }
        }

        return parent::_getScopeForAction($action);
    }

    protected function _assertViewingPermissions($action)
    {
        if ($action !== 'PostIndex') {
            parent::_assertViewingPermissions($action);
        }
    }

    protected function _assertBoardActive($action)
    {
        if ($action !== 'PostIndex') {
            parent::_assertBoardActive($action);
        }
    }

    protected function _assertTfaRequirement($action)
    {
        if ($action !== 'PostIndex') {
            parent::_assertTfaRequirement($action);
        }
    }

    public function _isFieldExcluded($field, array $prefixes = array(), $hasChild = true)
    {
        if ($field === 'user_id') {
            return false;
        }

        return parent::_isFieldExcluded($field, $prefixes);
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $params['user_id'] = $this->_request->getParam('user_id');
        $controllerName = 'XenForo_ControllerPublic_Member';
    }
}
