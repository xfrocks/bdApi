<?php

class bdApi_Extend_Model_User extends XFCP_bdApi_Extend_Model_User
{
    const CONDITIONS_USER_ID = 'bdApi_userId';
    const FETCH_IS_FOLLOWED = 'bdApi_followedUserId';
    const ORDER_USER_ID = 'bdApi_userId';

    public function bdApi_getLatestUserId()
    {
        return $this->_getDb()->fetchOne('
            SELECT user_id
            FROM xf_user
            ORDER BY user_id DESC
            LIMIT 1
        ');
    }

    public function bdApi_countUsersFollowingUserIds(array $userIds)
    {
        if (count($userIds) === 0) {
            return array();
        }

        return $this->_getDb()->fetchPairs('
            SELECT follow_user_id, COUNT(*)
            FROM xf_user_follow
            WHERE follow_user_id IN (' . $this->_getDb()->quote($userIds) . ')
            GROUP BY follow_user_id
        ');
    }

    public function bdApi_getSystemFields()
    {
        return array(
            'about',
            'homepage',
            'location',
            'occupation'
        );
    }

    public function prepareUserConditions(array $conditions, array &$fetchOptions)
    {
        $sqlConditions = array(parent::prepareUserConditions($conditions, $fetchOptions));

        if (isset($conditions[self::CONDITIONS_USER_ID])) {
            $sqlConditions[] = $this->getCutOffCondition('user.user_id', $conditions[self::CONDITIONS_USER_ID]);
        }

        if (count($sqlConditions) > 1) {
            return $this->getConditionsForClause($sqlConditions);
        } else {
            return $sqlConditions[0];
        }
    }

    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        $fetchOptions['join'] = XenForo_Model_User::FETCH_USER_FULL;

        $fetchOptions[self::FETCH_IS_FOLLOWED] = XenForo_Visitor::getUserId();

        return $fetchOptions;
    }

    public function prepareApiDataForUsers(array $users)
    {
        $data = array();

        foreach ($users as $key => $user) {
            $data[] = $this->prepareApiDataForUser($user);
        }

        return $data;
    }

    public function prepareApiDataForUser(array $user)
    {
        $visitor = XenForo_Visitor::getInstance();
        $session = bdApi_Data_Helper_Core::safeGetSession();
        /* @var $userGroupModel bdApi_Extend_Model_UserGroup */
        $userGroupModel = $this->getModelFromCache('XenForo_Model_UserGroup');
        /* @var $conversationModel XenForo_Model_Conversation */
        $conversationModel = $this->getModelFromCache('XenForo_Model_Conversation');

        $hasAdminScope = (!empty($session) && $session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM));
        $isAdminRequest = ($hasAdminScope && $visitor->hasAdminPermission('user'));
        $prepareProtectedData = (($user['user_id'] == $visitor->get('user_id')) || $isAdminRequest);
        $prepareExtendedData = $prepareProtectedData || $this->_getUserProfileModel()->canViewFullUserProfile($user);

        $publicKeys = array(
            // xf_user
            'user_id' => 'user_id',
            'username' => 'username',
        );

        if ($prepareExtendedData) {
            $publicKeys = array_merge($publicKeys, array(
                'message_count' => 'user_message_count',
                'register_date' => 'user_register_date',
                'like_count' => 'user_like_count',
            ));
        }

        if ($prepareProtectedData) {
            $publicKeys = array_merge($publicKeys, array(
                // xf_user
                'email' => 'user_email',
                'alerts_unread' => 'user_unread_notification_count',
                // xf_user_profile
                'dob_day' => 'user_dob_day',
                'dob_month' => 'user_dob_month',
                'dob_year' => 'user_dob_year',
            ));

            if (!empty($session) AND $session->checkScope(bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS)) {
                // xf_user
                $publicKeys['conversations_unread'] = 'user_unread_conversation_count';
            }
        }

        $data = bdApi_Data_Helper_Core::filter($user, $publicKeys);

        $data['user_is_followed'] = !empty($user['bdapi_user_is_followed']);
        $data['user_is_admin'] = !empty($user['is_admin']);
        $data['user_is_moderator'] = !empty($user['is_moderator']);
        $data['user_is_staff'] = !empty($user['is_staff']);
        $data['user_is_visitor'] = ($user['user_id'] == $visitor->get('user_id'));
        $data['user_title'] = XenForo_Template_Helper_Core::helperUserTitle($user);

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('members', $user),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $user),
            'avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array($user, 'm', false, true)),
            'avatar_big' => XenForo_Template_Helper_Core::callHelper('avatar', array($user, 'l', false, true)),
            'avatar_small' => XenForo_Template_Helper_Core::callHelper('avatar', array($user, 's', false, true)),
            'followers' => bdApi_Data_Helper_Core::safeBuildApiLink('users/followers', $user),
            'followings' => bdApi_Data_Helper_Core::safeBuildApiLink('users/followings', $user),
            'ignore' => bdApi_Data_Helper_Core::safeBuildApiLink('users/ignore', $user),
            'report' => bdApi_Data_Helper_Core::safeBuildApiLink('users/report', $user),
        );

        $data['permissions'] = array(
            'edit' => $prepareProtectedData,
            'follow' => ($user['user_id'] != $visitor->get('user_id')) && $visitor->canFollow(),
            'report' => $this->canReportUser($user),
        );

        /** @var XenForo_Model_UserIgnore $ignoreModel */
        $ignoreModel = $this->getModelFromCache('XenForo_Model_UserIgnore');
        $data['permissions']['ignore'] = $ignoreModel->canIgnoreUser($visitor->get('user_id'), $user);
        $data['user_is_ignored'] = $visitor->isIgnoring($user['user_id']);

        if ($prepareExtendedData) {
            $data['fields'] = $this->prepareApiDataForUserFields($user, $prepareProtectedData);

            if (isset($user['user_state']) AND isset($user['is_banned'])) {
                if (!empty($user['is_banned'])) {
                    $data['user_is_valid'] = false;
                    $data['user_is_verified'] = true;
                } else {
                    switch ($user['user_state']) {
                        case 'valid':
                            $data['user_is_valid'] = true;
                            $data['user_is_verified'] = true;
                            break;
                        case 'email_confirm':
                        case 'email_confirm_edit':
                            $data['user_is_valid'] = true;
                            $data['user_is_verified'] = false;
                            break;
                        case 'moderated':
                            $data['user_is_valid'] = false;
                            $data['user_is_verified'] = false;
                            break;
                    }
                }
            }

            if ($this->canViewUserCurrentActivity($user)) {
                $data['user_last_seen_date'] = $user['last_activity'];
            } else {
                // user hides his/her activity, use the register date value instead
                // (IMHO using 0 will make it too obvious that activity is hidden)
                $data['user_last_seen_date'] = $user['register_date'];
            }
        }

        if ($prepareProtectedData) {
            if (isset($user['timezone'])) {
                $dtz = new DateTimeZone($user['timezone']);
                $dt = new DateTime('now', $dtz);
                $data['user_timezone_offset'] = $dtz->getOffset($dt);
            }

            $auth = $this->getUserAuthenticationObjectByUserId($user['user_id']);
            $data['user_has_password'] = $auth->hasPassword();

            $thisUserGroups = array();
            $userGroups = $userGroupModel->bdApi_getAllUserGroupsCached();
            foreach ($userGroups as $userGroup) {
                if ($this->isMemberOfUserGroup($user, $userGroup['user_group_id'])) {
                    $thisUserGroups[] = $userGroup;
                }
            }
            $data['user_groups'] = $userGroupModel->prepareApiDataForUserGroups($thisUserGroups);

            foreach ($data['user_groups'] as &$userGroupRef) {
                if ($userGroupRef['user_group_id'] == $user['user_group_id']) {
                    $userGroupRef['is_primary_group'] = true;
                } else {
                    $userGroupRef['is_primary_group'] = false;
                }
            }

            if (isset($user['external_auth'])) {
                $externalAuths = @unserialize($user['external_auth']);
                if (is_array($externalAuths)) {
                    $data['user_external_authentications'] = array();
                    foreach ($externalAuths as $provider => $providerKey) {
                        $data['user_external_authentications'][] = array(
                            'provider' => $provider,
                            'provider_key' => $providerKey,
                        );
                    }
                }
            }

            $data['self_permissions'] = array(
                'create_conversation' => $conversationModel->canStartConversations(),
                'upload_attachment_conversation' => $conversationModel->canUploadAndManageAttachment(),
            );

            $data['edit_permissions'] = array(
                'password' => true,
                'user_email' => true,

                'username' => false,
                'user_title' => false,
                'primary_group_id' => false,
                'secondary_group_ids' => false,

                'user_dob_day' => false,
                'user_dob_month' => false,
                'user_dob_year' => false,

                'fields' => true,
            );

            if ($isAdminRequest) {
                $data['edit_permissions'] = array_merge($data['edit_permissions'], array(
                    'username' => true,
                    'user_title' => true,
                    'primary_group_id' => true,
                    'secondary_group_ids' => true,
                ));
            }

            if ((empty($data['user_dob_day'])
                    && empty($data['user_dob_month'])
                    && empty($data['user_dob_year']))
                || $isAdminRequest
            ) {
                $data['edit_permissions'] = array_merge($data['edit_permissions'], array(
                    'user_dob_day' => true,
                    'user_dob_month' => true,
                    'user_dob_year' => true,
                ));
            }
        }

        /** @var XenForo_Model_UserProfile $userProfileModel */
        $userProfileModel = $this->getModelFromCache('XenForo_Model_UserProfile');
        if ($userProfileModel->canViewProfilePosts($user)) {
            $data['links']['timeline'] = bdApi_Data_Helper_Core::safeBuildApiLink('users/timeline', $user);

            if ($user['user_id'] == $visitor->get('user_id')) {
                $data['permissions']['profile_post'] = $visitor->canUpdateStatus();
            } else {
                $data['permissions']['profile_post'] = $userProfileModel->canPostOnProfile($user);
            }
        }

        return $data;
    }

    public function prepareApiDataForUserFields(array $user, $prepareProtectedData = false)
    {
        $data = array();

        foreach ($this->bdApi_getSystemFields() as $systemFieldId) {
            $systemField = array(
                'id' => $systemFieldId,
                'title' => new XenForo_Phrase($systemFieldId),
                'description' => '',
                'position' => 'personal',
                'is_required' => false,
            );

            if (isset($user[$systemFieldId])) {
                $systemField['value'] = $user[$systemFieldId];
            }

            $data[] = $systemField;
        }

        if (!empty($user['custom_fields'])) {
            /** @var bdApi_Extend_Model_UserField $fieldModel */
            $fieldModel = $this->_getFieldModel();
            $fields = $fieldModel->bdApi_getUserFields();
            $values = unserialize($user['custom_fields']);

            foreach ($fields as $fieldId => $field) {
                $isProtectedData = false;
                $isProtectedData = $isProtectedData || $field['display_group'] === 'preferences';
                $isProtectedData = $isProtectedData || (empty($field['viewable_profile']) && empty($field['viewable_message']));
                if ($isProtectedData && $prepareProtectedData !== true) {
                    continue;
                }

                $fieldValue = isset($values[$fieldId]) ? $values[$fieldId] : null;
                $fieldData = $fieldModel->prepareApiDataForField($field, $fieldValue);
                $data[] = $fieldData;
            }
        }

        return $data;
    }

    public function prepareUserFetchOptions(array $fetchOptions)
    {
        $prepared = parent::prepareUserFetchOptions($fetchOptions);

        if (isset($fetchOptions[self::FETCH_IS_FOLLOWED])) {
            $fetchOptions[self::FETCH_IS_FOLLOWED] = intval($fetchOptions[self::FETCH_IS_FOLLOWED]);
            if ($fetchOptions[self::FETCH_IS_FOLLOWED]) {
                // note: quoting is skipped; intval'd above
                $prepared['selectFields'] .= ',
					IF(bdapi_user_follow.user_id IS NOT NULL, 1, 0) AS bdapi_user_is_followed';
                $prepared['joinTables'] .= '
					LEFT JOIN xf_user_follow AS bdapi_user_follow ON
						(bdapi_user_follow.user_id = ' . $fetchOptions[self::FETCH_IS_FOLLOWED] . '
						AND bdapi_user_follow.follow_user_id = user.user_id)';
            } else {
                $prepared['selectFields'] .= ',
					0 AS bdapi_user_is_followed';
            }
        }

        return $prepared;
    }

    public function getOrderByClause(array $choices, array $fetchOptions, $defaultOrderSql = '')
    {
        $choices[self::ORDER_USER_ID] = 'user.user_id';

        return parent::getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }
}
