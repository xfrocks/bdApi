<?php

class bdApi_XenForo_Model_Thread extends XFCP_bdApi_XenForo_Model_Thread 
{
	public function prepareApiDataForThreads(array $threads)
	{
		$data = array();
		
		foreach ($threads as $key => $thread)
		{
			$data[$key] = $this->prepareApiDataForThread($thread);
		}
		
		return $data;
	}
	
	public function prepareApiDataForThread(array $thread)
	{
		$publicKeys = array(
			// xf_thread
				'thread_id',
				'node_id',
				'title',
				'reply_count',
				'view_count',
				'user_id',
				'username',
				'post_date',
				'sticky',
				'discussion_state',
				'discussion_open',
				'discussion_type',
				'first_post_id',
				'first_post_likes',
				'last_post_date',
				'last_post_id',
				'last_post_user_id',
				'last_post_user_id',
				'last_post_username',
				'prefix_id',
			// other
				'thread_read_date',
				'user_post_count',
		);
		
		$data = bdApi_Data_Helper_Core::filter($thread, $publicKeys);
		
		$data['links'] = array(
			'permalink' => bdApi_Link::buildPublicLink('threads', $thread),
			'detail' => bdApi_Link::buildApiLink('threads', $thread),
			'forum' => bdApi_Link::buildApiLink('nodes', $thread),
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
		
		return $data;
	}
}