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

		$forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
		if (empty($forumId))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_requires_forum_id'));
		}

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable($forumId);

		$visitor = XenForo_Visitor::getInstance();

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

		$threads = $this->_getThreadModel()->getThreads($conditions, $fetchOptions);
		foreach ($threads AS &$thread)
		{
			$thread = $this->_getThreadModel()->prepareThread($thread, $forum);
		}
		$threads = array_values($threads);

		$total = $this->_getThreadModel()->countThreads($conditions);

		$data = array(
				'threads' => $this->_getThreadModel()->prepareApiDataForThreads($threads, $forum),
				'threads_total' => $total,
		);

		bdApi_Data_Helper_Core::addPageLinks($data, $limit, $total, $page, 'threads',
		array(), $pageNavParams);

		return $this->responseData('bdApi_ViewApi_Thread_List', $data);
	}

	public function actionGetSingle()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$data = array(
				'thread' => $this->_getThreadModel()->prepareApiDataForThread($thread, $forum),
		);

		return $this->responseData('bdApi_ViewApi_Thread_Single', $data);
	}

	public function actionPostIndex()
	{
		$forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$forum = $this->getHelper('ForumThreadPost')->assertForumValidAndViewable($forumId);

		$visitor = XenForo_Visitor::getInstance();

		if (!$this->_getForumModel()->canPostThreadInForum($forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		// the routine is very similar to XenForo_ControllerPublic_Forum::actionAddThread
		$input = $this->_input->filter(array(
				'thread_title' => XenForo_Input::STRING,
		));
		$input['post_body'] = $this->getHelper('Editor')->getMessageText('post_body', $this->_input);
		$input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

		// note: assumes that the message dw will pick up the username issues
		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$writer->bulkSet(array(
				'user_id'		=> $visitor['user_id'],
				'username'		=> $visitor['username'],
				'title'			=> $input['thread_title'],
				'node_id'		=> $forum['node_id'],
		));

		// discussion state changes instead of first message state
		$writer->set('discussion_state', $this->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array(), $forum));

		$postWriter = $writer->getFirstMessageDw();
		$postWriter->set('message', $input['post_body']);
		$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

		$writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);

		$writer->preSave();

		if (!$writer->hasErrors())
		{
			$this->assertNotFlooding('post');
		}

		$writer->save();

		$thread = $writer->getMergedData();

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], array(
				// TODO
				'watch_thread_state' => 0,
				'watch_thread' => 0,
				'watch_thread_email' => 0,
		));

		$this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);

		$this->_request->setParam('thread_id', $thread['thread_id']);
		return $this->responseReroute(__CLASS__, 'get-single');
	}

	public function actionDeleteIndex()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$deleteType = 'soft';
		$options = array(
				'reason' => '[bd] API',
		);

		if (!$this->_getThreadModel()->canDeleteThread($thread, $forum, $deleteType, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$this->_getThreadModel()->deleteThread($thread['thread_id'], $deleteType, $options);

		XenForo_Model_Log::logModeratorAction(
		'thread', $thread, 'delete_' . $deleteType, array('reason' => $options['reason'])
		);

		return $this->responseMessage(new XenForo_Phrase('bdapi_thread_x_has_been_deleted', array('thread_id' => $thread['thread_id'])));
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}
}