<?php

class bdApi_XenForo_Model_Post extends XFCP_bdApi_XenForo_Model_Post
{
	const FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE = 'bdApi_postsInThread_orderReverse';

	protected $_bdApi_postsInThread_orderReverse = false;

	protected static $_bdApi_posts = array();

	public function getPostsByIds(array $postIds, array $fetchOptions = array())
	{
		$posts = parent::getPostsByIds($postIds, $fetchOptions);

		self::$_bdApi_posts = $posts;

		return $posts;
	}

	public function getPostsInThread($threadId, array $fetchOptions = array())
	{
		if (!empty($fetchOptions[self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE]))
		{
			$this->_bdApi_postsInThread_orderReverse = true;
		}

		return parent::getPostsInThread($threadId, $fetchOptions);
	}

	public function fetchAllKeyed($sql, $key, $bind = array(), $nullPrefix = '')
	{
		if ($this->_bdApi_postsInThread_orderReverse)
		{
			$sql = str_replace('ORDER BY post.position ASC, post.post_date ASC', 'ORDER BY post.position DESC, post.post_date DESC', $sql, $count);

			if (empty($count))
			{
				throw new XenForo_Exception('Fatal Conflict: Could not change ORDER BY statement');
			}

			// reset the flag
			$this->_bdApi_postsInThread_orderReverse = false;
		}

		return parent::fetchAllKeyed($sql, $key, $bind, $nullPrefix);
	}

	public static function bdApi_getCachedPosts()
	{
		return self::$_bdApi_posts;
	}

	public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
	{
		$visitor = XenForo_Visitor::getInstance();

		if (empty($fetchOptions['join']))
		{
			$fetchOptions['join'] = XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_USER_PROFILE;
		}
		else
		{
			$fetchOptions['join'] |= XenForo_Model_Post::FETCH_USER;
			$fetchOptions['join'] |= XenForo_Model_Post::FETCH_USER_PROFILE;
		}

		$fetchOptions['likeUserId'] = $visitor->get('user_id');

		return $fetchOptions;
	}

	public function prepareApiDataForPosts(array $posts, array $thread, array $forum)
	{
		$data = array();

		foreach ($posts as $key => $post)
		{
			$data[] = $this->prepareApiDataForPost($post, $thread, $forum);
		}

		return $data;
	}

	public function prepareApiDataForPost(array $post, array $thread, array $forum)
	{
		$post = $this->preparePost($post, $thread, $forum);

		if (!isset($post['messageHtml']))
		{
			$post['messageHtml'] = $this->_renderApiMessage($post);
		}

		if (isset($post['message']))
		{
			$post['messagePlainText'] = bdApi_Data_Helper_Message::getPlainText($post['message']);
		}

		$publicKeys = array(
			// xf_post
			'post_id' => 'post_id',
			'thread_id' => 'thread_id',
			'user_id' => 'poster_user_id',
			'username' => 'poster_username',
			'post_date' => 'post_create_date',
			'message' => 'post_body',
			'messageHtml' => 'post_body_html',
			'messagePlainText' => 'post_body_plain_text',
			'likes' => 'post_like_count',
			'attach_count' => 'post_attachment_count',
		);

		$data = bdApi_Data_Helper_Core::filter($post, $publicKeys);

		if (isset($post['message_state']))
		{
			switch ($post['message_state'])
			{
				case 'visible':
					$data['post_is_published'] = true;
					$data['post_is_deleted'] = false;
					break;
				case 'moderated':
					$data['post_is_published'] = false;
					$data['post_is_deleted'] = false;
					break;
				case 'deleted':
					$data['post_is_published'] = false;
					$data['post_is_deleted'] = true;
					break;
			}
		}

		if (isset($post['position']))
		{
			$data['post_is_first_post'] = (intval($post['position']) === 0);
		}

		if (isset($post['like_date']))
		{
			$data['post_is_liked'] = !empty($post['like_date']);
		}

		if (!empty($post['attachments']))
		{
			$data['attachments'] = $this->prepareApiDataForAttachments($post, $post['attachments']);
		}

		$data['links'] = array(
			'permalink' => bdApi_Link::buildPublicLink('posts', $post),
			'detail' => bdApi_Link::buildApiLink('posts', $post),
			'thread' => bdApi_Link::buildApiLink('threads', $post),
			'poster' => bdApi_Link::buildApiLink('users', $post),
			'likes' => bdApi_Link::buildApiLink('posts/likes', $post),
			'poster_avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array(
				$post,
				'm',
				false,
				true
			)),
		);

		if (!empty($post['attach_count']))
		{
			$data['links']['attachments'] = bdApi_Link::buildApiLink('posts/attachments', $post);
		}

		$data['permissions'] = array(
			'view' => $this->canViewPost($post, $thread, $forum),
			'edit' => $this->canEditPost($post, $thread, $forum),
			'delete' => $this->canDeletePost($post, $thread, $forum),
			'reply' => $this->getModelFromCache('XenForo_Model_Thread')->canReplyToThread($thread, $forum),
			'like' => $this->canLikePost($post, $thread, $forum),
			'upload_attachment' => $this->canEditPost($post, $thread, $forum) AND $this->getModelFromCache('XenForo_Model_Forum')->canUploadAndManageAttachment($forum),
		);

		return $data;
	}

	public function prepareApiDataForAttachments(array $post, array $attachments, $tempHash = '')
	{
		$data = array();

		foreach ($attachments as $key => $attachment)
		{
			$data[] = $this->prepareApiDataForAttachment($post, $attachment, $tempHash);
		}

		return $data;
	}

	public function prepareApiDataForAttachment(array $post, array $attachment, $tempHash = '')
	{
		$attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
		$attachment = $attachmentModel->prepareAttachment($attachment);

		$publicKeys = array(
			// xf_attachment
			'attachment_id' => 'attachment_id',
			'content_id' => 'post_id',
			'view_count' => 'attachment_download_count',
			// xf_attachment_data
			'filename' => 'filename',
		);

		$data = bdApi_Data_Helper_Core::filter($attachment, $publicKeys);

		$paths = XenForo_Application::get('requestPaths');
		$paths['fullBasePath'] = XenForo_Application::getOptions()->get('boardUrl') . '/';

		$data['links'] = array('permalink' => bdApi_Link::buildPublicLink('attachments', $attachment));

		if (!empty($attachment['thumbnailUrl']))
		{
			$data['links']['thumbnail'] = bdApi_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true, $paths);
		}

		if (!empty($post['post_id']))
		{
			$data['links'] += array(
				'data' => bdApi_Link::buildApiLink('posts/attachments', $post, array('attachment_id' => $attachment['attachment_id'])),
				'post' => bdApi_Link::buildApiLink('posts', $post),
			);
		}

		$data['permissions'] = array(
			'view' => $attachmentModel->canViewAttachment($attachment, $tempHash),
			'delete' => $attachmentModel->canDeleteAttachment($attachment, $tempHash),
		);

		return $data;
	}

	protected function _renderApiMessage(array $post)
	{
		static $bbCodeParser = false;

		if ($bbCodeParser === false)
		{
			$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base'));
		}

		return new XenForo_BbCode_TextWrapper($post['message'], $bbCodeParser);
	}

}
