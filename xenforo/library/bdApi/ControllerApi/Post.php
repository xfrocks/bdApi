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
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $this->_getThreadModel()->getFetchOptionsToPrepareApiData(), $this->_getForumModel()->getFetchOptionsToPrepareApiData());

		$visitor = XenForo_Visitor::getInstance();

		if ($this->_getThreadModel()->isRedirect($thread))
		{
			return $this->responseError(new XenForo_Phrase('requested_thread_not_found'), 404);
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
			'deleted' => false,
			'moderated' => false,
			'limit' => $limit,
			'page' => $page
		);

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'natural'));
		switch ($order)
		{
			case 'natural_reverse':
				// load the class to make our constant accessible
				$this->_getPostModel();
				$fetchOptions[bdApi_XenForo_Model_Post::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE] = true;
				$pageNavParams['order'] = $order;
				break;
		}

		$posts = $this->_getPostModel()->getPostsInThread($threadId, $this->_getPostModel()->getFetchOptionsToPrepareApiData($fetchOptions));
		if (!$this->_isFieldExcluded('attachments'))
		{
			$posts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($posts);
		}

		$total = $thread['reply_count'] + 1;

		$data = array(
			'posts' => $this->_filterDataMany($this->_getPostModel()->prepareApiDataForPosts($posts, $thread, $forum)),
			'posts_total' => $total,
		);

		bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'posts', array(), $pageNavParams);

		return $this->responseData('bdApi_ViewApi_Post_List', $data);
	}

	public function actionGetSingle()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId, $this->_getPostModel()->getFetchOptionsToPrepareApiData(), $this->_getThreadModel()->getFetchOptionsToPrepareApiData(), $this->_getForumModel()->getFetchOptionsToPrepareApiData());

		if (!$this->_isFieldExcluded('attachments'))
		{
			$posts = array($post['post_id'] => $post);
			$posts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($posts);
			$post = reset($posts);
		}

		$data = array('post' => $this->_filterDataSingle($this->_getPostModel()->prepareApiDataForPost($post, $thread, $forum)), );

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
		$writer->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($thread));
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

		XenForo_Db::beginTransaction();

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$dw->setExistingData($post, true);
		$dw->set('message', $input['post_body']);
		$dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($post));
		$dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
		$dw->save();

		if ($post['post_id'] == $thread['first_post_id'] AND $this->_getThreadModel()->canEditThread($thread, $forum))
		{
			$threadInput = $this->_input->filter(array('thread_title' => XenForo_Input::STRING));

			$threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
			$threadDw->setExistingData($thread, true);

			if (!empty($threadInput['thread_title']))
			{
				$threadDw->set('title', $threadInput['thread_title']);
			}

			if ($threadDw->hasChanges())
			{
				$threadDw->save();
			}
		}

		XenForo_Db::commit();

		return $this->responseReroute(__CLASS__, 'get-single');
	}

	public function actionDeleteIndex()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

		$deleteType = 'soft';
		$options = array('reason' => '[bd] API', );

		if (!$this->_getPostModel()->canDeletePost($post, $thread, $forum, $deleteType, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$dw = $this->_getPostModel()->deletePost($postId, $deleteType, $options, $forum);

		if ($post['post_id'] == $thread['first_post_id'])
		{
			XenForo_Model_Log::logModeratorAction('thread', $thread, 'delete_' . $deleteType, array('reason' => $options['reason']));
		}
		else
		{
			XenForo_Model_Log::logModeratorAction('post', $post, 'delete_' . $deleteType, array('reason' => $options['reason']), $thread);
		}

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
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

		$data = array('users' => $users, );

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

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
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

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	public function actionGetAttachments()
	{
		$postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
		$attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId, $this->_getPostModel()->getFetchOptionsToPrepareApiData());

		$posts = array($post['post_id'] => $post);
		$posts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($posts);
		$post = reset($posts);

		if (empty($attachmentId))
		{
			$post = $this->_getPostModel()->prepareApiDataForPost($post, $thread, $forum);
			$attachments = isset($post['attachments']) ? $post['attachments'] : array();

			$data = array('attachments' => $this->_filterDataMany($attachments));
		}
		else
		{
			$attachments = isset($post['attachments']) ? $post['attachments'] : array();
			$attachment = false;

			foreach ($attachments as $_attachment)
			{
				if ($_attachment['attachment_id'] == $attachmentId)
				{
					$attachment = $_attachment;
				}
			}

			if (!empty($attachment))
			{
				return $this->_getAttachmentHelper()->doData($attachment);
			}
			else
			{
				return $this->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404);
			}
		}

		return $this->responseData('bdApi_ViewApi_Post_Attachments', $data);
	}

	public function actionPostAttachments()
	{
		$contentData = $this->_input->filter(array(
			'post_id' => XenForo_Input::UINT,
			'thread_id' => XenForo_Input::UINT,
		));
		if (empty($contentData['post_id']) AND empty($contentData['thread_id']))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_attachments_requires_ids'), 400);
		}

		$attachmentHelper = $this->_getAttachmentHelper();
		$hash = $attachmentHelper->getAttachmentTempHash($contentData);
		$response = $attachmentHelper->doUpload('file', $hash, 'post', $contentData);

		if ($response instanceof XenForo_ControllerResponse_Abstract)
		{
			return $response;
		}

		$data = array('attachment' => $this->_filterDataSingle($this->_getPostModel()->prepareApiDataForAttachment($contentData, $response, $hash)));

		return $this->responseData('bdApi_ViewApi_Post_Attachments', $data);
	}

	public function actionDeleteAttachments()
	{
		$contentData = $this->_input->filter(array(
			'post_id' => XenForo_Input::UINT,
			'thread_id' => XenForo_Input::UINT,
		));
		if (empty($contentData['post_id']) AND empty($contentData['thread_id']))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_attachments_requires_ids'), 400);
		}

		$attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

		$attachmentHelper = $this->_getAttachmentHelper();
		$hash = $attachmentHelper->getAttachmentTempHash($contentData);
		return $attachmentHelper->doDelete($hash, $attachmentId);
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

	/**
	 * @return bdApi_ControllerHelper_Attachment
	 */
	protected function _getAttachmentHelper()
	{
		return $this->getHelper('bdApi_ControllerHelper_Attachment');
	}

}
