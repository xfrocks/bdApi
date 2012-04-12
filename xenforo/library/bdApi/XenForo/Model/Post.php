<?php

class bdApi_XenForo_Model_Post extends XFCP_bdApi_XenForo_Model_Post
{
	public function prepareApiDataForPosts(array $posts)
	{
		$data = array();
		
		foreach ($posts as $key => $post)
		{
			$data[$key] = $this->prepareApiDataForPost($post);
		}
		
		return $data;
	}
	
	public function prepareApiDataForPost(array $post)
	{
		$publicKeys = array(
			// xf_post
				'post_id',
				'thread_id',
				'user_id',
				'username',
				'post_date',
				'messageHtml',
				'message_state',
				'attach_count',
				'position',
				'likes',
		);
		
		$data = bdApi_Data_Helper_Core::filter($post, $publicKeys);
		
		if (empty($data['messageHtml']))
		{
			$data['messageHtml'] = $this->_renderMessage($post);
		}
		
		$data['links'] = array(
			'permalink' => bdApi_Link::buildPublicLink('posts', $post),
			'detail' => bdApi_Link::buildApiLink('posts', $post),
			'thread' => bdApi_Link::buildApiLink('threads', $post),
			'poster' => bdApi_Link::buildApiLink('users', $post),
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