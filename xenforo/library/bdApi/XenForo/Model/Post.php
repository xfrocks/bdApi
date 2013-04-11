<?php

class bdApi_XenForo_Model_Post extends XFCP_bdApi_XenForo_Model_Post
{
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
		if (!isset($post['messageHtml']))
		{
			$post['messageHtml'] = $this->_renderMessage($post);
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

		$data['links'] = array(
				'permalink'			=> bdApi_Link::buildPublicLink('posts', $post),
				'detail'			=> bdApi_Link::buildApiLink('posts', $post),
				'thread'			=> bdApi_Link::buildApiLink('threads', $post),
				'poster'			=> bdApi_Link::buildApiLink('users', $post),
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