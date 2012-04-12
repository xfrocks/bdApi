<?php

class bdApi_ControllerApi_Post extends bdApi_ControllerApi_Abstract
{
	public function actionIndex()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		if (empty($threadId))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_requires_thread_id'));
		}
		
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);
		
		$visitor = XenForo_Visitor::getInstance();
		$threadModel = $this->_getThreadModel();
		$postModel = $this->_getPostModel();
		
		if ($threadModel->isRedirect($thread))
		{
			$redirect = $this->getModelFromCache('XenForo_Model_ThreadRedirect')->getThreadRedirectById($thread['thread_id']);
			if (!$redirect)
			{
				return $this->responseNoPermission();
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
					$redirect['target_url']
				);
			}
		}
		
		$pageNavParams = array('thread_id' => $thread['thread_id']);
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$limit = XenForo_Application::get('options')->messagesPerPage;
		
		$inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
		if (!empty($inputLimit))
		{
			$limit = $inputLimit;
			$pageNavParams['limit'] = $inputLimit;
		}
		
		$fetchOptions = array(
			'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_USER_PROFILE,
			'likeUserId' => $visitor['user_id'],
			'deleted' => false,
			'moderated' => false,
			'limit' => $limit,
			'page' => $page
		);
		
		$posts = $postModel->getPostsInThread($threadId, $fetchOptions);
		
		$permissions = $visitor->getNodePermissions($thread['node_id']);
		foreach ($posts AS &$post)
		{
			$post = $postModel->preparePost($post, $thread, $forum, $permissions);
		}
		
		$posts = array_values($posts);
		
		$total = $thread['reply_count'] + 1;
		
		$data = array(
			'posts' => $postModel->prepareApiDataForPosts($posts),
			'posts_total' => $total,
		);
		
		bdApi_Data_Helper_Core::addPageLinks($data, $limit, $total, $page, 'posts',
			array(), $pageNavParams);
		
		return $this->responseData('bdApi_ViewApi_Thread_List', $data);
	}
	
	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}
	
	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}
}