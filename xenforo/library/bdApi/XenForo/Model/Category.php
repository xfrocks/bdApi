<?php

class bdApi_XenForo_Model_Category extends XFCP_bdApi_XenForo_Model_Category
{
	public function prepareApiDataForCategories(array $categories)
	{
		$data = array();

		foreach ($categories as $key => $category)
		{
			$data[] = $this->prepareApiDataForCategory($category);
		}

		return $data;
	}

	public function prepareApiDataForCategory(array $category)
	{
		$publicKeys = array(
				// xf_node
				'node_id' 			=> 'category_id',
				'title' 			=> 'category_title',
				'description' 		=> 'category_description',
		);

		$data = bdApi_Data_Helper_Core::filter($category, $publicKeys);

		$data['links'] = array(
				'permalink' => bdApi_Link::buildPublicLink('categories', $category),
				'detail' => bdApi_Link::buildApiLink('categories', $category),
				'sub-categories' => bdApi_Link::buildApiLink('categories', array(), array('parent_category_id' => $category['node_id'])),
				'sub-forums' => bdApi_Link::buildApiLink('forums', array(), array('parent_category_id' => $category['node_id'])),
		);

		$data['permissions'] = array(
				'view' => $this->canViewCategory($category),
				'edit' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
				'delete' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
		);
			
		return $data;
	}
}