<?php

class bdApi_XenForo_Model_Node extends XFCP_bdApi_XenForo_Model_Node 
{
	public function prepareApiDataForNodes(array $nodes)
	{
		$data = array();
		
		foreach ($nodes as $key => $node)
		{
			$data[$key] = $this->prepareApiDataForNode($node);
		}
		
		return $data;
	}
	
	public function prepareApiDataForNode(array $node)
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
		
		$data = bdApi_Data_Helper_Core::filter($node, $publicKeys);
		
		$data['links'] = array(
			'detail' => bdApi_Link::buildApiLink('nodes', $node)
		);
		
		switch ($node['node_type_id'])
		{
			case 'Category':
				$data['links']['permalink'] = bdApi_Link::buildPublicLink('categories', $node);
				break;
			case 'Forum':
				$data['links']['permalink'] = bdApi_Link::buildPublicLink('forums', $node);
				$data['links']['threads'] = bdApi_Link::buildApiLink('threads', array(), array('node_id' => $node['node_id']));
				break;
			case 'Page':
				$data['links']['permalink'] = bdApi_Link::buildPublicLink('pages', $node);
				break;
		}
		
		return $data;
	}
}