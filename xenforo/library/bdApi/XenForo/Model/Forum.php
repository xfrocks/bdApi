<?php

class bdApi_XenForo_Model_Forum extends XFCP_bdApi_XenForo_Model_Forum
{
	public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
	{
		return $fetchOptions;
	}

	public function prepareApiDataForForums(array $forums)
	{
		$data = array();

		foreach ($forums as $key => $forum)
		{
			$data[] = $this->prepareApiDataForForum($forum);
		}

		return $data;
	}

	public function prepareApiDataForForum(array $forum)
	{
		$publicKeys = array(
				// xf_node
				'node_id'			=> 'forum_id',
				'title'				=> 'forum_title',
				'description'		=> 'forum_description',
				// xf_forum
				'discussion_count'	=> 'forum_thread_count',
				'message_count'		=> 'forum_post_count',
		);

		$data = bdApi_Data_Helper_Core::filter($forum, $publicKeys);

		$data['links'] = array(
				'permalink'			=> bdApi_Link::buildPublicLink('forums', $forum),
				'detail'			=> bdApi_Link::buildApiLink('forums', $forum),
				'sub-categories'	=> bdApi_Link::buildApiLink('categories', array(), array('parent_forum_id' => $forum['node_id'])),
				'sub-forums'		=> bdApi_Link::buildApiLink('forums', array(), array('parent_forum_id' => $forum['node_id'])),
				'threads'			=> bdApi_Link::buildApiLink('threads', array(), array('forum_id' => $forum['node_id'])),
		);
		
		$data['permissions'] = array(
				'view' 				=> $this->canViewForum($forum),
				'edit' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
				'delete' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
				'create_thread' 	=> $this->canPostThreadInForum($forum),
		);
			
		return $data;
	}
}