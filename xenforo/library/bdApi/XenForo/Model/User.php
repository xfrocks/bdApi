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
			$data[$key] = $this->prepareApiDataForUser($user);
		}

		return $data;
	}

	public function prepareApiDataForUser(array $user)
	{
		$publicKeys = array(
				// xf_user
				'user_id'			=> 'user_id',
				'username'			=> 'username',
				'custom_title'		=> 'user_title',
				'message_count'		=> 'user_message_count',
				'register_date'		=> 'user_register_date',
				'like_count'		=> 'user_like_count',
		);

		if ($user['user_id'] == XenForo_Visitor::getUserId())
		{
			$publicKeys = array_merge($publicKeys, array(
					// xf_user
					'email'			=> 'user_email',
					// xf_user_profile
					'dob_day'		=> 'user_dob_day',
					'dob_month'		=> 'user_dob_month',
					'dob_year'		=> 'user_dob_year',
			));
		}

		$data = bdApi_Data_Helper_Core::filter($user, $publicKeys);

		$data['links'] = array(
				'permalink' => bdApi_Link::buildPublicLink('members', $user),
				'detail' => bdApi_Link::buildApiLink('users', $user),
		);

		if ($user['user_id'] == XenForo_Visitor::getUserId())
		{
			if (isset($user['timezone']))
			{
				$dtz = new DateTimeZone($user['timezone']);
				$dt = new DateTime('now', $dtz);
				$data['user_timezone_offset'] = $dtz->getOffset($dt);
			}
		}

		return $data;
	}

	public function getOrderByClause(array $choices, array $fetchOptions, $defaultOrderSql = '')
	{
		$choices[self::ORDER_USER_ID] = 'user.user_id';

		return parent::getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}