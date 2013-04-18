<?php

abstract class bdApi_ControllerApi_Node extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		if (!empty($nodeId))
		{
			return $this->responseReroute($this->_getControllerName(), 'get-single');
		}

		$parentId = $this->_input->filterSingle('parent_category_id', XenForo_Input::STRING);
		if ($parentId === '')
		{
			$parentId = $this->_input->filterSingle('parent_forum_id', XenForo_Input::STRING);
		}
		if ($parentId === '')
		{
			$parentId = false;
		}
		else
		{
			$parentId = intval($parentId);
		}

		$nodes = $this->_getAll($parentId);

		$data = array(
				$this->_getNamePlural() => $this->_prepareApiDataForNodes($nodes),
				$this->_getNamePlural() . '_total' => count($nodes),
		);

		return $this->responseData('bdApi_ViewApi_Node_List', $data);
	}

	public function actionGetSingle()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);

		$node = $this->_getSingle($nodeId);

		if (empty($node) OR !$this->_isViewable($node))
		{
			return $this->_responseNodeNotFound();
		}

		$data = array(
				$this->_getNameSingular() => $this->_prepareApiDataForNode($node),
		);

		return $this->responseData('bdApi_ViewApi_Node_Single', $data);
	}

	/**
	 * @return XenForo_Model_Node
	 */
	protected function _getNodeModel()
	{
		return $this->getModelFromCache('XenForo_Model_Node');
	}

	abstract protected function _getControllerName();

	abstract protected function _getNameSingular();

	abstract protected function _getNamePlural();

	abstract protected function _getAll($parentNodeId = false);

	abstract protected function _getSingle($nodeId);

	abstract protected function _isViewable(array $node);

	abstract protected function _prepareApiDataForNodes(array $nodes);

	abstract protected function _prepareApiDataForNode(array $node);

	abstract protected function _responseErrorNotFound();
}