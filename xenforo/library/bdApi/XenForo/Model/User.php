<?php

class bdApi_XenForo_Model_User extends XFCP_bdApi_XenForo_Model_User
{
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
					'email'			=> 'email',
					// xf_user_profile
					'dob_day'		=> 'dob_day',
					'dob_month'		=> 'dob_month',
					'dob_year'		=> 'dob_year',
			));
		}

		$data = bdApi_Data_Helper_Core::filter($user, $publicKeys);

		$data['links'] = array(
				'permalink' => bdApi_Link::buildPublicLink('members', $user),
				'detail' => bdApi_Link::buildApiLink('users', $user),
		);

		return $data;
	}
}