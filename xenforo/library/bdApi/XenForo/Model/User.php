<?php

class bdApi_XenForo_Model_User extends XFCP_bdApi_XenForo_Model_User
{
	const ORDER_USER_ID = 'bdApi_user_id';

	public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
	{
		$fetchOptions['join'] = XenForo_Model_User::FETCH_USER_FULL;

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

		$publicKeys = array(
			// xf_user
			'user_id' => 'user_id',
			'username' => 'username',
			'custom_title' => 'user_title',
			'message_count' => 'user_message_count',
			'register_date' => 'user_register_date',
			'like_count' => 'user_like_count',
		);

		if ($user['user_id'] == $visitor->get('user_id'))
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

		$data['links'] = array(
			'permalink' => bdApi_Link::buildPublicLink('members', $user),
			'detail' => bdApi_Link::buildApiLink('users', $user),
			'avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array(
				$user,
				'm',
				false,
				true
			)),
			'followers' => bdApi_Link::buildApiLink('users/followers', $user),
			'followings' => bdApi_Link::buildApiLink('users/followings', $user),
		);

		$data['permissions'] = array('follow' => ($user['user_id'] != $visitor->get('user_id')) AND $visitor->canFollow());

		if ($user['user_id'] == $visitor->get('user_id'))
		{
			$data['user_is_visitor'] = true;

			if (isset($user['timezone']))
			{
				$dtz = new DateTimeZone($user['timezone']);
				$dt = new DateTime('now', $dtz);
				$data['user_timezone_offset'] = $dtz->getOffset($dt);
			}

			$auth = $this->getUserAuthenticationObjectByUserId($user['user_id']);
			$data['user_has_password'] = $auth->hasPassword();

			$data['user_custom_fields'] = !empty($user['custom_fields']) ? unserialize($user['custom_fields']) : array();

			$data['self_permissions'] = array('create_conversation' => $this->getModelFromCache('XenForo_Model_Conversation')->canStartConversations());
		}
		else
		{
			$data['user_is_visitor'] = false;
		}

		return $data;
	}

	public function getOrderByClause(array $choices, array $fetchOptions, $defaultOrderSql = '')
	{
		$choices[self::ORDER_USER_ID] = 'user.user_id';

		return parent::getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}

}
