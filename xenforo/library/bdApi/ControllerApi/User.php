<?php

class bdApi_ControllerApi_User extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		if (!empty($userId))
		{
			return $this->responseReroute(__CLASS__, 'get-single');
		}

		$userModel = $this->_getUserModel();

		$pageNavParams = array();
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$limit = XenForo_Application::get('options')->membersPerPage;

		$inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
		if (!empty($inputLimit))
		{
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

		$users = $userModel->getUsers(
				$conditions,
				$userModel->getFetchOptionsToPrepareApiData($fetchOptions)
		);

		$total = $userModel->countUsers($conditions);

		$data = array(
				'users' => $this->_filterDataMany($userModel->prepareApiDataForUsers($users)),
				'users_total' => $total,
		);

		bdApi_Data_Helper_Core::addPageLinks($data, $limit, $total, $page, 'users',
		array(), $pageNavParams);

		return $this->responseData('bdApi_ViewApi_User_List', $data);
	}

	public function actionGetSingle()
	{
		$user = $this->_getUserOrError();

		$data = array(
				'user' => $this->_filterDataSingle($this->_getUserModel()->prepareApiDataForUser($user)),
		);

		return $this->responseData('bdApi_ViewApi_User_Single', $data);
	}

	public function actionPostIndex()
	{
		$input = $this->_input->filter(array(
				'email' => XenForo_Input::STRING,
				'username' => XenForo_Input::STRING,
				'password' => XenForo_Input::STRING,
				'user_dob_day' => XenForo_Input::UINT,
				'user_dob_month' => XenForo_Input::UINT,
				'user_dob_year' => XenForo_Input::UINT,
		));
		$userModel = $this->_getUserModel();
		$options = XenForo_Application::getOptions();
		$session = XenForo_Application::getSession();
		$visitor = XenForo_Visitor::getInstance();

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($options->registrationDefaults)
		{
			$writer->bulkSet($options->registrationDefaults, array('ignoreInvalidFields' => true));
		}
		$writer->set('email', $input['email']);
		$writer->set('username', $input['username']);
		$writer->setPassword($input['password'], $input['password']);
		if ($options->gravatarEnable && XenForo_Model_Avatar::gravatarExists($input['email']))
		{
			$writer->set('gravatar', $input['email']);
		}

		$writer->set('dob_day', $input['user_dob_day']);
		$writer->set('dob_month', $input['user_dob_month']);
		$writer->set('dob_year', $input['user_dob_year']);

		$writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
		$writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));

		$writer->advanceRegistrationUserState();

		if ($visitor->hasAdminPermission('user') AND $session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM))
		{
			$writer->set('user_state', 'valid');
		}

		$writer->save();

		$user = $writer->getMergedData();

		// log the ip of the user registering
		XenForo_Model_Ip::log(XenForo_Visitor::getUserId() ? XenForo_Visitor::getUserId() : $user['user_id'], 'user', $user['user_id'], 'register');

		if ($user['user_state'] == 'email_confirm')
		{
			$this->getModelFromCache('XenForo_Model_UserConfirmation')->sendEmailConfirmation($user);
		}

		$this->_request->setParam('user_id', $user['user_id']);
		return $this->responseReroute(__CLASS__, 'get-single');
	}

	public function actionPostAvatar()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$visitor = XenForo_Visitor::getInstance();

		if ($userId != $visitor->get('user_id'))
		{
			return $this->responseNoPermission();
		}

		if (!$visitor->canUploadAvatar())
		{
			return $this->responseNoPermission();
		}

		$avatar = XenForo_Upload::getUploadedFile('avatar');
		if (empty($avatar))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_requires_upload_x', array('field' => 'avatar')), 400);
		}

		$avatarData = $this->getModelFromCache('XenForo_Model_Avatar')->uploadAvatar(
				$avatar,
				$visitor->get('user_id'),
				$visitor->getPermissions()
		);

		return $this->responseMessage(new XenForo_Phrase('upload_completed_successfully'));
	}

	public function actionDeleteAvatar()
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		$visitor = XenForo_Visitor::getInstance();

		if ($userId != $visitor->get('user_id'))
		{
			return $this->responseNoPermission();
		}

		if (!$visitor->canUploadAvatar())
		{
			return $this->responseNoPermission();
		}

		$this->getModelFromCache('XenForo_Model_Avatar')->deleteAvatar($visitor->get('user_id'));

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	public function actionGetFollowers()
	{
		$user = $this->_getUserOrError();
		$userModel = $this->_getUserModel();

		$followers = $userModel->getUsersFollowingUserId($user['user_id'], 0, 'user.user_id');

		$data = array(
				'users' => array(),
		);

		foreach ($followers as $follower)
		{
			$data['users'][] = array('user_id' => $follower['user_id']);
		}

		return $this->responseData('bdApi_ViewApi_User_Followers', $data);
	}

	public function actionPostFollowers()
	{
		$user = $this->_getUserOrError();
		$visitor = XenForo_Visitor::getInstance();

		if (($user['user_id'] == $visitor->get('user_id')) OR !$visitor->canFollow())
		{
			return $this->responseNoPermission();
		}

		$this->_getUserModel()->follow($user);

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	public function actionDeleteFollowers()
	{
		$user = $this->_getUserOrError();
		$visitor = XenForo_Visitor::getInstance();

		if (($user['user_id'] == $visitor->get('user_id')) OR !$visitor->canFollow())
		{
			return $this->responseNoPermission();
		}

		$this->_getUserModel()->unfollow($user['user_id']);

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	public function actionGetFollowings()
	{
		$user = $this->_getUserOrError();
		$userModel = $this->_getUserModel();

		$followings = $userModel->getFollowedUserProfiles($user['user_id'], 0, 'user.user_id');

		$data = array(
				'users' => array(),
		);

		foreach ($followings as $following)
		{
			$data['users'][] = array('user_id' => $following['user_id']);
		}

		return $this->responseData('bdApi_ViewApi_User_Followings', $data);
	}

	public function actionGetMe()
	{
		if (XenForo_Visitor::getUserId() == 0)
		{
			return $this->responseNoPermission();
		}

		$this->_request->setParam('user_id', XenForo_Visitor::getUserId());
		return $this->responseReroute(__CLASS__, 'get-single');
	}

	public function actionPostMeAvatar()
	{
		if (XenForo_Visitor::getUserId() == 0)
		{
			return $this->responseNoPermission();
		}

		$this->_request->setParam('user_id', XenForo_Visitor::getUserId());
		return $this->responseReroute(__CLASS__, 'post-avatar');
	}

	public function actionDeleteMeAvatar()
	{
		if (XenForo_Visitor::getUserId() == 0)
		{
			return $this->responseNoPermission();
		}

		$this->_request->setParam('user_id', XenForo_Visitor::getUserId());
		return $this->responseReroute(__CLASS__, 'delete-avatar');
	}

	public function actionGetMeFollowers()
	{
		if (XenForo_Visitor::getUserId() == 0)
		{
			return $this->responseNoPermission();
		}

		$this->_request->setParam('user_id', XenForo_Visitor::getUserId());
		return $this->responseReroute(__CLASS__, 'get-followers');
	}

	public function actionGetMeFollowings()
	{
		if (XenForo_Visitor::getUserId() == 0)
		{
			return $this->responseNoPermission();
		}

		$this->_request->setParam('user_id', XenForo_Visitor::getUserId());
		return $this->responseReroute(__CLASS__, 'get-followings');
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}

	protected function _getUserOrError(array $fetchOptions = array())
	{
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);

		$userModel = $this->_getUserModel();

		$user = $userModel->getUserById(
				$userId,
				$userModel->getFetchOptionsToPrepareApiData($fetchOptions)
		);

		if (empty($user))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_user_not_found'), 404));
		}

		return $user;
	}

	protected function _getScopeForAction($action)
	{
		if ($action === 'PostIndex')
		{
			$session = XenForo_Application::getSession();
			$clientId = $session->getOAuthClientId();

			if (empty($clientId))
			{
				return false;
			}
		}

		return parent::_getScopeForAction($action);
	}
}