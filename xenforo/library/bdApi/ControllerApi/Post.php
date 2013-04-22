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
			return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_requires_thread_id'), 400);
		}

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$visitor = XenForo_Visitor::getInstance();

		if ($this->_getThreadModel()->isRedirect($thread))
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

		$posts = $this->_getPostModel()->getPostsInThread($threadId, $fetchOptions);
		foreach ($posts AS &$post)
		{
			$post = $this->_getPostModel()->preparePost($post, $thread, $forum);
		}
		$posts = array_values($posts);

		$total = $thread['reply_count'] + 1;

		$data = array(
				'posts' => $this->_getPostModel()->prepareApiDataForPosts($posts, $thread, $forum),
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
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId, array(
				'likeUserId' => XenForo_Visitor::getUserId(),
		));

		$visitor = XenForo_Visitor::getInstance();

		$post = $this->_getPostModel()->preparePost($post, $thread, $forum);

		$data = array(
				'post' => $this->_getPostModel()->prepareApiDataForPost($post, $thread, $forum),
		);

		return $this->responseData('bdApi_ViewApi_Post_Single', $data);
	}

	public function actionPostIndex()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

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
		$writer->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

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

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], array(
				// TODO
				'watch_thread_state' => 0,
				'watch_thread' => 0,
				'watch_thread_email' => 0,
		));

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

		$deleteType = 'soft';
		$options = array(
				'reason' => '[bd] API',
		);

		if (!$this->_getPostModel()->canDeletePost($post, $thread, $forum, $deleteType, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$dw = $this->_getPostModel()->deletePost($postId, $deleteType, $options, $forum);

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

	public function actionGetLikes()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$likes = $this->_getLikeModel()->getContentLikes('post', $post['post_id']);
		$users = array();

		if (!empty($likes))
		{
			foreach ($likes as $like)
			{
				$users[] = array(
						'user_id' => $like['like_user_id'],
						'username' => $like['username'],
				);
			}
		}

		$data = array(
				'users' => $users,
		);

		return $this->responseData('bdApi_ViewApi_Post_Likes', $data);
	}

	public function actionPostLikes()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		if (!$this->_getPostModel()->canLikePost($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$likeModel = $this->_getLikeModel();

		$existingLike = $likeModel->getContentLikeByLikeUser('post', $postId, XenForo_Visitor::getUserId());
		if (empty($existingLike))
		{
			$latestUsers = $likeModel->likeContent('post', $postId, $post['user_id']);

			if ($latestUsers === false)
			{
				return $this->responseNoPermission();
			}
		}

		return $this->responseMessage(new XenForo_Phrase('bdapi_post_x_has_been_liked', array('post_id' => $post['post_id'])));
	}

	public function actionDeleteLikes()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		if (!$this->_getPostModel()->canLikePost($post, $thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$likeModel = $this->_getLikeModel();

		$existingLike = $likeModel->getContentLikeByLikeUser('post', $postId, XenForo_Visitor::getUserId());
		if (!empty($existingLike))
		{
			$latestUsers = $likeModel->unlikeContent($existingLike);

			if ($latestUsers === false)
			{
				return $this->responseNoPermission();
			}
		}

		return $this->responseMessage(new XenForo_Phrase('bdapi_post_x_has_been_unliked', array('post_id' => $post['post_id'])));
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
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}

	/**
	 * @return XenForo_Model_Like
	 */
	protected function _getLikeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Like');
	}
}