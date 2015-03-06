<?php

class bdApi_ControllerApi_Navigation extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $parent = $this->_input->filterSingle('parent', XenForo_Input::STRING);

        $elements = $this->_getElements($parent);

        $viewParams = array(
            'elements' => $elements,
            'elements_count' => count($elements),
        );

        return $this->responseData('bdApi_ViewApi_Navigation_Index', $viewParams);
    }

    protected function _getElements($parent)
    {
        if (is_numeric($parent)) {
            $parentNode = $this->_getNodeModel()->getNodeById($parent);
            $expectedParentNodeId = $parentNode['node_id'];
        } else {
            $parentNode = false;
            $expectedParentNodeId = 0;
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

            foreach ($nodeList['nodesGrouped'] as $parentNodeId => $nodes) {
                if ($parentNodeId != $expectedParentNodeId) {
                    continue;
                }

                foreach ($nodes as $node) {
                    $element = false;

                    switch ($node['node_type_id']) {
                        case 'Category':
                            $element = $this->_getCategoryModel()->prepareApiDataForCategory($node);
                            break;
                        case 'Forum':
                            if (!empty($forums[$node['node_id']])) {
                                $element = $this->_getForumModel()->prepareApiDataForForum($forums[$node['node_id']]);
                            }
                            break;
                        case 'LinkForum':
                            $element = $this->_getLinkForumModel()->prepareApiDataForLinkForum($node);
                            break;
                    }

                    if (!empty($element)) {
                        $element['navigation_type'] = strtolower($node['node_type_id']);
                        $element['navigation_id'] = $node['node_id'];

                        $element['has_sub_elements'] = !empty($nodeList['nodesGrouped'][$node['node_id']]);
                        if ($element['has_sub_elements']) {
                            if (empty($element['links'])) {
                                $element['links'] = array();
                            }

                            $element['links']['sub-elements'] = XenForo_Link::buildApiLink('navigation', '', array('parent' => $element['navigation_id']));
                        }

                        $elements[] = $element;
                    }
                }
            }
        }

        return $elements;
    }

    /**
     * @return bdApi_XenForo_Model_Category
     */
    protected function _getCategoryModel()
    {
        return $this->getModelFromCache('XenForo_Model_Category');
    }

    /**
     * @return bdApi_XenForo_Model_Forum
     */
    protected function _getForumModel()
    {
        return $this->getModelFromCache('XenForo_Model_Forum');
    }

    /**
     * @return bdApi_XenForo_Model_LinkForum
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

}
