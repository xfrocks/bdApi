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
			'join' => XenForo_Model_User::FETCH_USER_FULL,
			'limit' => $limit,
			'page' => $page,
			'order' => bdApi_XenForo_Model_User::ORDER_USER_ID,
		);
		
		$users = $userModel->getUsers($conditions, $fetchOptions);
		$users = array_values($users);
		
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
		$userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
		
		$userModel = $this->_getUserModel();
		
		$fetchOptions = array(
			'join' => XenForo_Model_User::FETCH_USER_FULL,
		);
		
		$user = $userModel->getUserById($userId, $fetchOptions);
		
		if (empty($user))
		{
			return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
		}

		$data = array(
			'user' => $this->_filterDataSingle($userModel->prepareApiDataForUser($user)),
		);
		
		return $this->responseData('bdApi_ViewApi_User_Single', $data);
	}
	
	public function actionGetMe()
	{
		$this->_request->setParam('user_id', XenForo_Visitor::getUserId());
		return $this->responseReroute(__CLASS__, 'get-single');
	}
	
	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->getModelFromCache('XenForo_Model_User');
	}
}