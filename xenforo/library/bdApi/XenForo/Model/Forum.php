<?php

class bdApi_XenForo_Model_Forum extends XFCP_bdApi_XenForo_Model_Forum 
{
	public function prepareApiDataForForums(array $forums)
	{
		$data = array();
		
		foreach ($forums as $key => $forum)
		{
			$data[$key] = $this->prepareApiDataForForum($forum);
		}
		
		return $data;
	}
	
	public function prepareApiDataForForum(array $forum)
	{
		$publicKeys = array(
			// xf_node
				'node_id',
				'title',
				'description',
				'node_name',
				'node_type_id',
				'parent_node_id',
				'display_order',
				'depth',
		);
		
		$data = bdApi_Data_Helper_Core::filter($forum, $publicKeys);
		
		$data['links'] = array(
			'permalink' => bdApi_Link::buildPublicLink('forums', $forum),
			'detail' => bdApi_Link::buildApiLink('forums', $forum),
			'sub-categories' => bdApi_Link::buildApiLink('categories', array(), array('parent_forum_id' => $forum['node_id'])),
			'sub-forums' => bdApi_Link::buildApiLink('forums', array(), array('parent_forum_id' => $forum['node_id'])),
			'threads' => bdApi_Link::buildApiLink('threads', array(), array('forum_id' => $forum['node_id'])),
		);
			
		return $data;
	}
}