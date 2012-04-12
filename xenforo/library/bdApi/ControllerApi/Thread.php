<?php

class bdApi_ControllerApi_Thread extends bdApi_ControllerApi_Abstract
{
	public function actionIndex()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		if (!empty($threadId))
		{
			return $this->responseReroute(__CLASS__, 'single');
		}
		
		$visitor = XenForo_Visitor::getInstance();
		$nodeModel = $this->_getNodeModel();
		$threadModel = $this->_getThreadModel();
		
		$pageNavParams = array();
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
		);
		$fetchOptions = array(
			'join' => XenForo_Model_Thread::FETCH_USER,
			'readUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id'],
			'limit' => $limit,
			'page' => $page
		);
		
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		if (empty($nodeId))
		{
			$viewableNodes = $nodeModel->getViewableNodeList();
			$conditions['node_id'] = array_keys($viewableNodes);
		}
		else
		{
			$ftpHelper = $this->getHelper('ForumThreadPost');
			$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable($nodeId);
			$conditions['node_id'] = $forum['node_id'];
			$pageNavParams['node_id'] = $forum['node_id'];
		}
		
		$threads = $threadModel->getThreads($conditions, $fetchOptions);
		$threads = array_values($threads);
		
		$total = $threadModel->countThreads($conditions);
		
		$data = array(
			'threads' => $threadModel->prepareApiDataForThreads($threads),
			'threads_total' => $total,
		);
		
		if (!empty($forum))
		{
			$data['node'] = $nodeModel->prepareApiDataForNode($forum);
		}
		
		bdApi_Data_Helper_Core::addPageLinks($data, $limit, $total, $page, 'threads',
			array(), $pageNavParams);
		
		return $this->responseData('bdApi_ViewApi_Thread_List', $data);
	}
	
	public function actionSingle()
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