<?php

class bdApi_ControllerApi_Post extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
		if (!empty($postId))
		{
			return $this->responseReroute(__CLASS__, 'get-single');
		}

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
		foreach ($posts AS &$post)
		{
			$post = $postModel->preparePost($post, $thread, $forum);
		}
		$posts = array_values($posts);

		$total = $thread['reply_count'] + 1;

		$data = array(
				'posts' => $postModel->prepareApiDataForPosts($posts, $thread, $forum),
				'posts_total' => $total,
		);

		bdApi_Data_Helper_Core::addPageLinks($data, $limit, $total, $page, 'posts',
		array(), $pageNavParams);

		return $this->responseData('bdApi_ViewApi_Post_List', $data);
	}

	public function actionGetSingle()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$visitor = XenForo_Visitor::getInstance();
		$threadModel = $this->_getThreadModel();
		$postModel = $this->_getPostModel();

		$post = $postModel->preparePost($post, $thread, $forum);

		$data = array(
				'post' => $postModel->prepareApiDataForPost($post, $thread, $forum),
		);

		return $this->responseData('bdApi_ViewApi_Post_Single', $data);
	}

	public function actionPostIndex()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		if (empty($threadId))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_requires_thread_id'));
		}

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$visitor = XenForo_Visitor::getInstance();

		if (!$this->_getThreadModel()->canReplyToThread($thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		// the routine is very similar to XenForo_ControllerPublic_Thread::actionAddReply
		$input = $this->_input->filter(array(
				// TODO
		));
		$input['post_body'] = $this->getHelper('Editor')->getMessageText('post_body', $this->_input);
		$input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$writer->set('user_id', $visitor['user_id']);
		$writer->set('username', $visitor['username']);
		$writer->set('message', $input['post_body']);
		$writer->set('message_state', $this->_getPostModel()->getPostInsertMessageState($thread, $forum));
		$writer->set('thread_id', $thread['thread_id']);

		$clientId = XenForo_Application::getSession()->getOAuthClientId();
		if (!empty($clientId))
		{
			$writer->set('bdapi_origin', $clientId);
		}

		$writer->preSave();

		if (!$writer->hasErrors())
		{
			$this->assertNotFlooding('post');
		}

		$writer->save();
		$post = $writer->getMergedData();

		$this->_request->setParam('post_id', $post['post_id']);
		return $this->responseReroute(__CLASS__, 'get-single');
	}

	public function actionPutIndex()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		if (!$this->_getPostModel()->canEditPost($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$input = $this->_input->filter(array(
				// TODO
		));
		$input['post_body'] = $this->getHelper('Editor')->getMessageText('post_body', $this->_input);
		$input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$dw->setExistingData($post['post_id']);
		$dw->set('message', $input['post_body']);
		$dw->save();

		return $this->responseReroute(__CLASS__, 'get-single');
	}

	public function actionDeleteIndex()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$postModel = $this->_getPostModel();
		$deleteType = 'soft';
		$options = array(
				'reason' => '[bd] API',
		);

		if (!$postModel->canDeletePost($post, $thread, $forum, $deleteType, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$dw = $postModel->deletePost($postId, $deleteType, $options, $forum);

		if ($post['post_id'] == $thread['first_post_id'])
		{
			XenForo_Model_Log::logModeratorAction(
			'thread', $thread, 'delete_' . $deleteType, array('reason' => $options['reason'])
			);
		}
		else
		{
			XenForo_Model_Log::logModeratorAction(
			'post', $post, 'delete_' . $deleteType, array('reason' => $options['reason']), $thread
			);
		}

		return $this->responseMessage(new XenForo_Phrase('bdapi_post_x_has_been_deleted', array('post_id' => $post['post_id'])));
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