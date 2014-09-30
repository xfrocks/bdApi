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

		$order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'natural', ));
		switch ($order)
		{
			case 'list':
				usort($nodes, create_function('$a, $b', 'return ($a["lft"] == $b["lft"] ? 0 : ($a["lft"] < $b["lft"] ? -1 : 1));'));
				break;
		}

		$data = array(
			$this->_getNamePlural() => $this->_filterDataMany($this->_prepareApiDataForNodes($nodes)),
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
			return $this->_responseErrorNotFound();
		}

		$data = array($this->_getNameSingular() => $this->_filterDataSingle($this->_prepareApiDataForNode($node)), );

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
