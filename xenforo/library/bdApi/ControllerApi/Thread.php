<?php

class bdApi_ControllerApi_Thread extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		if (!empty($threadId))
		{
			return $this->responseReroute(__CLASS__, 'get-single');
		}
		
		$categoryId = $this->_input->filterSingle('category_id', XenForo_Input::UINT);
		if (!empty($categoryId))
		{
			return $this->_responseDataThreads(array(), 0);
		}
		
		$forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
		if (empty($forumId))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_requires_forum_id'));
		}

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable($forumId);
		
		$visitor = XenForo_Visitor::getInstance();
		$nodeModel = $this->_getNodeModel();
		$threadModel = $this->_getThreadModel();
		
		$pageNavParams = array(
			'forum_id' => $forum['node_id'],
		);
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$limit = XenForo_Application::get('options')->discussionsPerPage;
		
		$inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
		if (!empty($inputLimit))
		{
			$limit = $inputLimit;
			$pageNavParams['limit'] = $inputLimit;
		}
		
		$conditions = array(
			'deleted' => false,
			'moderated' => false,
			'node_id' => $forum['node_id'],
		);
		$fetchOptions = array(
			'join' => XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
			'limit' => $limit,
			'page' => $page
		);
		
		$threads = $threadModel->getThreads($conditions, $fetchOptions);
		$threads = array_values($threads);
		
		$total = $threadModel->countThreads($conditions);
		
		return $this->_responseDataThreads($threads, $total, $limit, $page, $pageNavParams);
	}
	
	public function actionGetSingle()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);
		
		$nodeModel = $this->_getNodeModel();
		$threadModel = $this->_getThreadModel();

		$data = array(
			'thread' => $threadModel->prepareApiDataForThread($thread),
		);
		
		return $this->responseData('bdApi_ViewApi_Thread_Single', $data);
	}
	
	protected function _responseDataThreads($threads, $total, $limit = 0, $page = 1, $pageNavParams = array())
	{
		$data = array(
			'threads' => $this->_getThreadModel()->prepareApiDataForThreads($threads),
			'threads_total' => $total,
		);

		bdApi_Data_Helper_Core::addPageLinks($data, $limit, $total, $page, 'threads',
		array(), $pageNavParams);

		return $this->responseData('bdApi_ViewApi_Thread_List', $data);
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}
	
	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
}