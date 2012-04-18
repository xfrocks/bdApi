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
				'user_id',
				'username',
				'gender',
				'custom_title',
				'language_id',
				'style_id',
				'timezone',
				'message_count',
				'register_date',
				'trophy_points',
				'avatar_date',
				'avatar_width',
				'avatar_height',
				'gravatar',
				'like_count',
		);
		
		$data = bdApi_Data_Helper_Core::filter($user, $publicKeys);
		
		$data['links'] = array(
			'permalink' => bdApi_Link::buildPublicLink('members', $user),
			'detail' => bdApi_Link::buildApiLink('users', $user),
		);
		
		return $data;
	}
}