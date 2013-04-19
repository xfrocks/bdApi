<?php

class bdApi_ControllerApi_Search extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$data = array(
				'links' => array(
						'posts' 			=> bdApi_Link::buildApiLink('search/posts'),
						'threads' 			=> bdApi_Link::buildApiLink('search/threads'),
				),
		);

		return $this->responseData('bdApi_ViewApi_Index', $data);
	}

	public function actionGetThreads()
	{
		return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
	}

	public function actionPostThreads()
	{
		$constraints = array();

		$rawResults = $this->_doSearch('thread', $constraints);

		$results = array();
		foreach ($rawResults as $rawResult)
		{
			$results[] = array(
					'thread_id' => $rawResult[1],
			);
		}

		$data = array(
				'threads' => $results,
		);

		return $this->responseData('bdApi_ViewApi_Search_Threads', $data);
	}

	public function actionGetPosts()
	{
		return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
	}


	public function actionPostPosts()
	{
		$constraints = array();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		if (!empty($threadId))
		{
			$constraints['thread'] = $threadId;
		}

		$this->_doSearch('post', $constraints);

		// perform get posts from model because the search result are groupped
		$this->_getPostModel();
		$posts = bdApi_XenForo_Model_Post::bdApi_getCachedPosts();
		$posts = array_values($posts);

		$results = array();
		foreach ($posts as $post)
		{
			$results[] = array(
					'post_id' => $post['post_id'],
			);
		}

		$data = array(
				'posts' => $results,
		);

		return $this->responseData('bdApi_ViewApi_Search_Posts', $data);
	}

	public function _doSearch($contentType, array $constraints = array())
	{
		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$input = array();

		$input['keywords'] = $this->_input->filterSingle('q', XenForo_Input::STRING);
		$input['keywords'] = XenForo_Helper_String::censorString($input['keywords'], null, ''); // don't allow searching of censored stuff
		if (empty($input['keywords']))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_slash_search_requires_q'), 400));
		}

		$limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
		$maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
		if ($limit > 0)
		{
			$maxResults = min($maxResults, $limit);
		}

		$forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
		if (!empty($forumId))
		{
			$childNodeIds = array_keys($this->getModelFromCache('XenForo_Model_Node')->getChildNodesForNodeIds(array($forumId)));
			$nodeIds = array_unique(array_merge(array($forumId), $childNodeIds));
			$constraints['node'] = implode(' ', $nodeIds);
			if (!$constraints['node'])
			{
				unset($constraints['node']); // just 0
			}
		}

		$visitorUserId = XenForo_Visitor::getUserId();
		$searchModel = $this->_getSearchModel();

		$typeHandler = $searchModel->getSearchDataHandler($contentType);

		$searcher = new XenForo_Search_Searcher($searchModel);

		return $searcher->searchType($typeHandler, $input['keywords'], $constraints, 'relevance', false, $maxResults);
	}

	protected function _getScopeForAction($action)
	{
		return bdApi_Model_OAuth2::SCOPE_READ;
	}

	/**
	 * @return XenForo_Model_Search
	 */
	protected function _getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
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

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}
}