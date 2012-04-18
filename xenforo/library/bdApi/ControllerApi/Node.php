<?php

class bdApi_ControllerApi_Node extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		if (!empty($nodeId))
		{
			return $this->responseReroute(__CLASS__, 'get-single');
		}
		
		$nodeModel = $this->_getNodeModel();
		
		$nodes = $nodeModel->getViewableNodeList();
		$nodes = array_values($nodes);
		
		$data = array(
			'nodes' => $nodeModel->prepareApiDataForNodes($nodes),
			'nodes_total' => count($nodes),
		);
		
		return $this->responseData('bdApi_ViewApi_Node_List', $data);
	}
	
	public function actionGetSingle()
	{
		$nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		
		$nodeModel = $this->_getNodeModel();
		
		$visitor = XenForo_Visitor::getInstance();
		
		$fetchOptions = array(
			'permissionCombinationId' => $visitor['permission_combination_id'],
		);
		
		$node = $nodeModel->getNodeById($nodeId, $fetchOptions);
		
		if (empty($node))
		{
			return $this->responseError(new XenForo_Phrase('requested_node_not_found'), 404);
		}
		
		$nodeHandlers = $nodeModel->getNodeHandlersForNodeTypes(
			$nodeModel->getUniqueNodeTypeIdsFromNodeList(array($node))
		);
		
		$nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination();
		$thisNodePermissions = (isset($nodePermissions[$node['node_id']]) ? $nodePermissions[$node['node_id']] : array());
		
		if (empty($nodeHandlers[$node['node_type_id']])
			OR !$nodeHandlers[$node['node_type_id']]->isNodeViewable($node, $thisNodePermissions))
		{
			return $this->responseNoPermission();
		}

		$data = array(
			'node' => $nodeModel->prepareApiDataForNode($node),
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
}