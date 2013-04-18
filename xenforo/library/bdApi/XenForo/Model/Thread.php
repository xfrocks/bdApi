<?php

class bdApi_XenForo_Model_Thread extends XFCP_bdApi_XenForo_Model_Thread
{
	protected static $_bdApi_threads = array();

	public function getThreadsByIds(array $threadIds, array $fetchOptions = array())
	{
		$threads = parent::getThreadsByIds($threadIds, $fetchOptions);

		self::$_bdApi_threads = $threads;

		return $threads;
	}

	public static function bdApi_getCachedThreads()
	{
		return self::$_bdApi_threads;
	}

	public function prepareApiDataForThreads(array $threads, array $forum)
	{
		$data = array();

		foreach ($threads as $key => $thread)
		{
			$data[$key] = $this->prepareApiDataForThread($thread, $forum);
		}

		return $data;
	}

	public function prepareApiDataForThread(array $thread, array $forum)
	{
		$publicKeys = array(
				// xf_thread
				'thread_id'			=> 'thread_id',
				'node_id'			=> 'forum_id',
				'title'				=> 'thread_title',
				'view_count'		=> 'thread_view_count',
				'user_id'			=> 'creator_user_id',
				'username'			=> 'creator_username',
				'post_date'			=> 'thread_create_date',
				'last_post_date'	=> 'thread_update_date',
		);

		$data = bdApi_Data_Helper_Core::filter($thread, $publicKeys);

		if (isset($thread['reply_count']))
		{
			$data['thread_post_count'] = $thread['reply_count'] + 1;
		}

		if (isset($thread['sticky']) AND isset($thread['discussion_state']))
		{
			switch ($thread['discussion_state'])
			{
				case 'visible':
					$data['thread_is_published'] = true;
					$data['thread_is_deleted'] = false;
					$data['thread_is_sticky'] = empty($thread['sticky']) ? false : true;
					break;
				case 'moderated':
					$data['thread_is_published'] = false;
					$data['thread_is_deleted'] = false;
					$data['thread_is_sticky'] = false;
					break;
				case 'deleted':
					$data['thread_is_published'] = false;
					$data['ithread_s_deleted'] = true;
					$data['thread_is_sticky'] = false;
					break;
			}
		}

		$data['links'] = array(
				'permalink' => bdApi_Link::buildPublicLink('threads', $thread),
				'detail' => bdApi_Link::buildApiLink('threads', $thread),
				'forum' => bdApi_Link::buildApiLink('forums', $thread),
				'posts' => bdApi_Link::buildApiLink('posts', array(), array('thread_id' => $thread['thread_id'])),
				'first_poster' => bdApi_Link::buildApiLink('users', $thread),
				'first_post' => bdApi_Link::buildApiLink('posts', array('post_id' => $thread['first_post_id'])),
		);

		if ($thread['last_post_user_id'] != $thread['user_id'])
		{
			$data['links']['last_poster'] = bdApi_Link::buildApiLink('users', array('user_id' => $thread['last_post_user_id'], 'username' => $thread['last_post_username']));
		}

		if ($thread['last_post_id'] != $thread['first_post_id'])
		{
			$data['links']['last_post'] = bdApi_Link::buildApiLink('posts', array('post_id' => $thread['last_post_id']));
		}

		$data['permissions'] = array(
				'view'				=> $this->canViewThread($thread, $forum),
				'edit'				=> $this->canEditThread($thread, $forum),
				'delete'			=> $this->canDeleteThread($thread, $forum),
				'post'				=> $this->canReplyToThread($thread, $forum),
		);

		return $data;
	}
}