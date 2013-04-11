<?php

class bdApi_ControllerApi_Forum extends bdApi_ControllerApi_Node
{
	protected function _getControllerName()
	{
		return 'bdApi_ControllerApi_Forum';
	}

	protected function _getNameSingular()
	{
		return 'forum';
	}

	protected function _getNamePlural()
	{
		return 'forums';
	}

	protected function _getAll($parentNodeId)
	{
		$nodes = $this->_getNodeModel()->getViewableNodeList();

		$forums = array();
		foreach ($nodes as $node)
		{
			if ($parentNodeId > 0 AND $node['parent_node_id'] != $parentNodeId)
			{
				continue;
			}

			if ($node['node_type_id'] === 'Forum')
			{
				$forums[] = $node;
			}
		}

		return $forums;
	}

	protected function _getSingle($nodeId)
	{
		return $this->_getForumModel()->getForumById($nodeId);
	}

	protected function _isViewable(array $forum)
	{
		return $this->_getForumModel()->canViewForum($forum);
	}

	protected function _prepareApiDataForNodes(array $forums)
	{
		return $this->_getForumModel()->prepareApiDataForForums($forums);
	}

	protected function _prepareApiDataForNode(array $forum)
	{
		return $this->_getForumModel()->prepareApiDataForForum($forum);
	}

	protected function _responseErrorNotFound()
	{
		return $this->responseError(new XenForo_Phrase('requested_forum_not_found'), 404);
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}
}