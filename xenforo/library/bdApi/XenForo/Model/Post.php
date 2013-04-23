<?php

class bdApi_XenForo_Model_Post extends XFCP_bdApi_XenForo_Model_Post
{
	protected static $_bdApi_posts = array();

	public function getPostsByIds(array $postIds, array $fetchOptions = array())
	{
		$posts = parent::getPostsByIds($postIds, $fetchOptions);

		self::$_bdApi_posts = $posts;

		return $posts;
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
			$data[$key] = $this->prepareApiDataForPost($post, $thread, $forum);
		}

		return $data;
	}

	public function prepareApiDataForPost(array $post, array $thread, array $forum)
	{
		$post = $this->preparePost($post, $thread, $forum);

		if (!isset($post['messageHtml']))
		{
			$post['messageHtml'] = $this->_renderMessage($post);
		}

		if (isset($post['message']))
		{
			$post['messagePlainText'] = XenForo_Template_Helper_Core::callHelper('snippet', array($post['message']));
		}

		$publicKeys = array(
				// xf_post
				'post_id'			=> 'post_id',
				'thread_id'			=> 'thread_id',
				'user_id'			=> 'poster_user_id',
				'username'			=> 'poster_username',
				'post_date'			=> 'post_create_date',
				'message'			=> 'post_body',
				'messageHtml'		=> 'post_body_html',
				'messagePlainText'	=> 'post_body_plain_text',
				'likes'				=> 'post_like_count',
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

		if (in_array('like_date', array_keys($post)))
		{
			$data['post_is_liked'] = !empty($post['like_date']);
		}

		$data['links'] = array(
				'permalink'			=> bdApi_Link::buildPublicLink('posts', $post),
				'detail'			=> bdApi_Link::buildApiLink('posts', $post),
				'thread'			=> bdApi_Link::buildApiLink('threads', $post),
				'poster'			=> bdApi_Link::buildApiLink('users', $post),
				'likes'				=> bdApi_Link::buildApiLink('posts/likes', $post),
		);

		$data['permissions'] = array(
				'view'				=> $this->canViewPost($post, $thread, $forum),
				'edit'				=> $this->canEditPost($post, $thread, $forum),
				'delete'			=> $this->canDeletePost($post, $thread, $forum),
				'like'				=> $this->canLikePost($post, $thread, $forum),
		);

		return $data;
	}

	protected function _renderMessage(array $post)
	{
		static $bbCodeParser = false;

		if ($bbCodeParser === false)
		{
			$bbCodeParser = new XenForo_BbCode_Parser(XenForo_BbCode_Formatter_Base::create('Base'));
		}

		return new XenForo_BbCode_TextWrapper($post['message'], $bbCodeParser);
	}
}