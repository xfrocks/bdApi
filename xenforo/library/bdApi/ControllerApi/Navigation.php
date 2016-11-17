<?php

class bdApi_ControllerApi_Navigation extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $parent = $this->_input->filterSingle('parent', XenForo_Input::STRING);

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
        $elements = array();

        if (!empty($nodeList['nodesGrouped'])) {
            $forumIds = array();
            $forums = array();
            foreach ($nodeList['nodesGrouped'] as $parentNodeId => $nodes) {
                foreach ($nodes as $node) {
                    if ($node['node_type_id'] === 'Forum') {
                        $forumIds[] = $node['node_id'];
                    }
                }
            }

            if (!empty($forumIds)) {
                $forums = $this->_getForumModel()->getForumsByIds($forumIds, $this->_getForumModel()->getFetchOptionsToPrepareApiData());
            }

            $arrangeOptions = array(
                'expectedParentNodeId' => $expectedParentNodeId,
                'forums' => $forums,
            );
            $this->_arrangeElements(
                $elements,
                $nodeList['nodesGrouped'],
                is_int($expectedParentNodeId) ? $expectedParentNodeId : 0,
                $arrangeOptions
            );
        }

        return $elements;
    }

    protected function _arrangeElements(array &$elements, array &$nodesGrouped, $parentNodeId, array &$options = array())
    {
        foreach ($nodesGrouped as $_parentNodeId => $nodes) {
            if ($parentNodeId != $_parentNodeId) {
                continue;
            }

            foreach ($nodes as $node) {
                $element = false;

                switch ($node['node_type_id']) {
                    case 'Category':
                        $element = $this->_getCategoryModel()->prepareApiDataForCategory($node);
                        break;
                    case 'Forum':
                        if (!empty($options['forums'][$node['node_id']])) {
                            $element = $this->_getForumModel()->prepareApiDataForForum($options['forums'][$node['node_id']]);
                        }
                        break;
                    case 'LinkForum':
                        $element = $this->_getLinkForumModel()->prepareApiDataForLinkForum($node);
                        break;
                    case 'Page':
                        $element = $this->_getPageModel()->prepareApiDataForPage($node);
                        break;
                }

                if (!empty($element)) {
                    $element['navigation_type'] = strtolower($node['node_type_id']);
                    $element['navigation_id'] = $node['node_id'];
                    $element['navigation_parent_id'] = $node['parent_node_id'];

                    $element['has_sub_elements'] = !empty($nodesGrouped[$node['node_id']]);
                    if ($element['has_sub_elements']) {
                        if (empty($element['links'])) {
                            $element['links'] = array();
                        }

                        $element['links']['sub-elements'] = bdApi_Data_Helper_Core::safeBuildApiLink('navigation', '', array('parent' => $element['navigation_id']));
                    }

                    $elements[] = $element;

                    if ($element['has_sub_elements'] && !is_int($options['expectedParentNodeId'])) {
                        $this->_arrangeElements($elements, $nodesGrouped, intval($node['node_id']), $options);
                    }
                }
            }
        }
    }

    /**
     * @return bdApi_Extend_Model_Category
     */
    protected function _getCategoryModel()
    {
        return $this->getModelFromCache('XenForo_Model_Category');
    }

    /**
     * @return bdApi_Extend_Model_Forum
     */
    protected function _getForumModel()
    {
        return $this->getModelFromCache('XenForo_Model_Forum');
    }

    /**
     * @return bdApi_Extend_Model_LinkForum
     */
    protected function _getLinkForumModel()
    {
        return $this->getModelFromCache('XenForo_Model_LinkForum');
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Node');
    }

    /**
     * @return bdApi_Extend_Model_Page
     */
    protected function _getPageModel()
    {
        return $this->getModelFromCache('XenForo_Model_Page');
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'XenForo_ControllerPublic_Forum';
    }
}
