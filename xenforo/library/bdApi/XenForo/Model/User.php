<?php

class bdApi_XenForo_Model_User extends XFCP_bdApi_XenForo_Model_User
{
	const FETCH_IS_FOLLOWED = 'bdApi_followedUserId';
	const ORDER_USER_ID = 'bdApi_user_id';

	public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
	{
		$fetchOptions['join'] = XenForo_Model_User::FETCH_USER_FULL;

		$fetchOptions[self::FETCH_IS_FOLLOWED] = XenForo_Visitor::getUserId();

		return $fetchOptions;
	}

	public function prepareApiDataForUsers(array $users)
	{
		$data = array();

		foreach ($users as $key => $user)
		{
			$data[] = $this->prepareApiDataForUser($user);
		}

		return $data;
	}

	public function prepareApiDataForUser(array $user)
	{
		$visitor = XenForo_Visitor::getInstance();
		$hasAdminScope = XenForo_Application::getSession()->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM);
		$isAdminRequest = ($hasAdminScope AND $visitor->hasAdminPermission('user'));
		$prepareProtectedData = (($user['user_id'] == $visitor->get('user_id')) OR $isAdminRequest);

		$publicKeys = array(
			// xf_user
			'user_id' => 'user_id',
			'username' => 'username',
			'custom_title' => 'user_title',
			'message_count' => 'user_message_count',
			'register_date' => 'user_register_date',
			'like_count' => 'user_like_count',
		);

		if ($prepareProtectedData)
		{
			$publicKeys = array_merge($publicKeys, array(
				// xf_user
				'email' => 'user_email',
				'alerts_unread' => 'user_unread_notification_count',
				// xf_user_profile
				'dob_day' => 'user_dob_day',
				'dob_month' => 'user_dob_month',
				'dob_year' => 'user_dob_year',
			));

			if (XenForo_Application::getSession()->checkScope(bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS))
			{
				// xf_user
				$publicKeys['conversations_unread'] = 'user_unread_conversation_count';
			}
		}

		$data = bdApi_Data_Helper_Core::filter($user, $publicKeys);

		if (isset($user['user_state']) AND isset($user['is_banned']))
		{
			if (!empty($user['is_banned']))
			{
				$data['user_is_valid'] = false;
				$data['user_is_verified'] = true;
			}
			else
			{
				switch ($user['user_state'])
				{
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

		$data['user_is_followed'] = !empty($user['bdapi_user_is_followed']);

		$data['links'] = array(
			'permalink' => XenForo_Link::buildPublicLink('members', $user),
			'detail' => XenForo_Link::buildApiLink('users', $user),
			'avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array(
				$user,
				'm',
				false,
				true
			)),
			'avatar_big' => XenForo_Template_Helper_Core::callHelper('avatar', array(
				$user,
				'l',
				false,
				true
			)),
			'followers' => XenForo_Link::buildApiLink('users/followers', $user),
			'followings' => XenForo_Link::buildApiLink('users/followings', $user),
		);

		$data['permissions'] = array('follow' => ($user['user_id'] != $visitor->get('user_id')) AND $visitor->canFollow());

		$data['user_is_visitor'] = ($user['user_id'] == $visitor->get('user_id'));

		if ($prepareProtectedData)
		{
			if (isset($user['timezone']))
			{
				$dtz = new DateTimeZone($user['timezone']);
				$dt = new DateTime('now', $dtz);
				$data['user_timezone_offset'] = $dtz->getOffset($dt);
			}

			$auth = $this->getUserAuthenticationObjectByUserId($user['user_id']);
			$data['user_has_password'] = $auth->hasPassword();

			if (!empty($user['custom_fields']))
			{
				$data['user_custom_fields'] = unserialize($user['custom_fields']);
				if (empty($data['user_custom_fields']))
				{
					unset($data['user_custom_fields']);
				}
			}

			if ($isAdminRequest)
			{
				$userGroupModel = $this->getModelFromCache('XenForo_Model_UserGroup');
				$thisUserGroups = array();
				$userGroups = $userGroupModel->bdApi_getAllUserGroupsCached();
				foreach ($userGroups as $userGroup)
				{
					if ($this->isMemberOfUserGroup($user, $userGroup['user_group_id']))
					{
						$thisUserGroups[] = $userGroup;
					}
				}
				$data['user_groups'] = $userGroupModel->prepareApiDataForUserGroups($thisUserGroups);
				
				foreach ($data['user_groups'] as &$userGroupRef)
				{
					if ($userGroupRef['user_group_id'] == $user['user_group_id'])
					{
						$userGroupRef['is_primary_group'] = true;
					}
					else
					{
						$userGroupRef['is_primary_group'] = false;
					}
				}
			}

			$data['self_permissions'] = array(
				'create_conversation' => $this->getModelFromCache('XenForo_Model_Conversation')->canStartConversations(),
				'upload_attachment_conversation' => $this->getModelFromCache('XenForo_Model_Conversation')->canUploadAndManageAttachment(),
			);
		}

		return $data;
	}

	public function prepareUserFetchOptions(array $fetchOptions)
	{
		$prepared = parent::prepareUserFetchOptions($fetchOptions);
		extract($prepared);

		if (isset($fetchOptions[self::FETCH_IS_FOLLOWED]))
		{
			$fetchOptions[self::FETCH_IS_FOLLOWED] = intval($fetchOptions[self::FETCH_IS_FOLLOWED]);
			if ($fetchOptions[self::FETCH_IS_FOLLOWED])
			{
				// note: quoting is skipped; intval'd above
				$selectFields .= ',
					IF(user_follow.user_id IS NOT NULL, 1, 0) AS bdapi_user_is_followed';
				$joinTables .= '
					LEFT JOIN xf_user_follow AS user_follow ON
						(user_follow.user_id = ' . $fetchOptions[self::FETCH_IS_FOLLOWED] . ' AND user_follow.follow_user_id = user.user_id)';
			}
			else
			{
				$selectFields .= ',
					0 AS bdapi_user_is_followed';
			}
		}

		return compact(array_keys($prepared));
	}

	public function getOrderByClause(array $choices, array $fetchOptions, $defaultOrderSql = '')
	{
		$choices[self::ORDER_USER_ID] = 'user.user_id';

		return parent::getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

}
