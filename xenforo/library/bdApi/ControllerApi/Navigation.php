<?php

class bdApi_ControllerApi_Navigation extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $parent = $this->_input->filterSingle('parent', XenForo_Input::STRING);

        $visitor = XenForo_Visitor::getInstance();
        $nodePermissions = $this->_getNodeModel()->getNodePermissionsForPermissionCombination();
        foreach ($nodePermissions as $nodeId => $permissions) {
            $visitor->setNodePermissions($nodeId, $permissions);
        }

        $elements = $this->_getElements($parent);

        $viewParams = array(
            'elements' => $this->_filterDataMany($elements),
            'elements_count' => count($elements),
        );

        return $this->responseData('bdApi_ViewApi_Navigation_Index', $viewParams);
    }

    protected function _getElements($parent)
    {
        if (is_numeric($parent)) {
            if ($parent > 0) {
                // return children of specified element
                $parentNode = $this->_getNodeModel()->getNodeById($parent);
                if (empty($parentNode)) {
                    throw $this->responseException($this->responseError(
                        new XenForo_Phrase('bdapi_navigation_element_not_found'),
                        404
                    ));
                }

                $expectedParentNodeId = intval($parentNode['node_id']);
            } else {
                // return root elements
                $parentNode = false;
                $expectedParentNodeId = 0;
            }
        } else {
            // return all viewable elements
            $parentNode = false;
            $expectedParentNodeId = null;
        }

        $nodeList = $this->_getNodeModel()->getNodeDataForListDisplay($parentNode, 0);
        if (empty($nodeList['nodesGrouped'])) {
            return array();
        }

        /** @var bdApi_ControllerHelper_Navigation $helper */
        $helper = $this->getHelper('bdApi_ControllerHelper_Navigation');
        return $helper->prepareElements($nodeList['nodesGrouped'], $expectedParentNodeId);
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Node');
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'XenForo_ControllerPublic_Forum';
    }
}
