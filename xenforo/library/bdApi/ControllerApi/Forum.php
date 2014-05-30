<?php

class bdApi_ControllerApi_Forum extends bdApi_ControllerApi_Node
{
	public function actionGetFollowed()
	{
		$forums = array();

		if (XenForo_Application::$versionId >= 1020000)
		{
			$forumWatchModel = $this->getModelFromCache('XenForo_Model_ForumWatch');

			$forumWatches = $forumWatchModel->getUserForumWatchByUser(XenForo_Visitor::getUserId());

			$nodeIds = array();
			foreach ($forumWatches as $forumWatch)
			{
				$nodeIds[] = $forumWatch['node_id'];
			}

			$forums = $this->_getForumModel()->getForumsByIds($nodeIds, $this->_getForumModel()->getFetchOptionsToPrepareApiData());
			$forums = $this->_getForumModel()->prepareApiDataForForums($forums);

			foreach ($forumWatches as $forumWatch)
			{
				foreach ($forums as &$forum)
				{
					if ($forumWatch['node_id'] == $forum['forum_id'])
					{
						$forum = $forumWatchModel->prepareApiDataForForumWatches($forum, $forumWatch);
					}
				}
			}
		}

		$data = array('forums' => $this->_filterDataMany($forums));

		return $this->responseData('bdApi_ViewApi_Forum_Followed', $data);
	}

	public function actionGetFollowers()
	{
		$users = array();

		if (XenForo_Application::$versionId >= 1020000)
		{
			$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

			$ftpHelper = $this->getHelper('ForumThreadPost');
			$forum = $ftpHelper->assertForumValidAndViewable($nodeId);

			if ($this->_getForumModel()->canWatchForum($forum))
			{
				$visitor = XenForo_Visitor::getInstance();
				$forumWatchModel = $this->getModelFromCache('XenForo_Model_ForumWatch');

				$forumWatch = $forumWatchModel->getUserForumWatchByForumId($visitor['user_id'], $forum['node_id']);

				if (!empty($forumWatch))
				{
					$user = array(
						'user_id' => $visitor['user_id'],
						'username' => $visitor['username'],
					);

					$user = $forumWatchModel->prepareApiDataForForumWatches($user, $forumWatch);

					$users[] = $user;
				}
			}
		}

		$data = array('users' => $this->_filterDataMany($users));

		return $this->responseData('bdApi_ViewApi_Forum_Followers', $data);
	}

	public function actionPostFollowers()
	{
		if (XenForo_Application::$versionId < 1020000)
		{
			return $this->responseNoPermission();
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$post = $this->_input->filterSingle('post', XenForo_Input::UINT);
		$sendAlert = $this->_input->filterSingle('alert', XenForo_Input::UINT, array('default' => 1));
		$sendEmail = $this->_input->filterSingle('email', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable($nodeId);

		if (!$this->_getForumModel()->canWatchForum($forum))
		{
			return $this->responseNoPermission();
		}

		$notifyOn = ($post > 0 ? 'message' : 'thread');
		$this->getModelFromCache('XenForo_Model_ForumWatch')->setForumWatchState(XenForo_Visitor::getUserId(), $forum['node_id'], $notifyOn, $sendAlert, $sendEmail);

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	public function actionDeleteFollowers()
	{
		if (XenForo_Application::$versionId < 1020000)
		{
			return $this->responseNoPermission();
		}

		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$this->getModelFromCache('XenForo_Model_ForumWatch')->setForumWatchState(XenForo_Visitor::getUserId(), $nodeId, 'delete');

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	protected function _getControllerName()
	{
		return 'bdApi_ControllerApi_Forum';
	}

	protected function _getNameSingular()
	{
		return 'forum';
	}

	protected function _getNamePlural()
	{
		return 'forums';
	}

	protected function _getAll($parentNodeId = false)
	{
		$nodes = $this->_getNodeModel()->getViewableNodeList();

		$forumIds = array();
		foreach ($nodes as $node)
		{
			if ($parentNodeId !== false AND $node['parent_node_id'] != $parentNodeId)
			{
				continue;
			}

			if ($node['node_type_id'] === 'Forum')
			{
				$forumIds[] = $node['node_id'];
			}
		}

		return $this->_getForumModel()->getForumsByIds($forumIds, $this->_getForumModel()->getFetchOptionsToPrepareApiData());
	}

	protected function _getSingle($nodeId)
	{
		return $this->_getForumModel()->getForumById($nodeId, $this->_getForumModel()->getFetchOptionsToPrepareApiData());
	}

	protected function _isViewable(array $forum)
	{
		return $this->_getForumModel()->canViewForum($forum);
	}

	protected function _prepareApiDataForNodes(array $forums)
	{
		return $this->_getForumModel()->prepareApiDataForForums($forums);
	}

	protected function _prepareApiDataForNode(array $forum)
	{
		return $this->_getForumModel()->prepareApiDataForForum($forum);
	}

	protected function _responseErrorNotFound()
	{
		return $this->responseError(new XenForo_Phrase('requested_forum_not_found'), 404);
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

}
