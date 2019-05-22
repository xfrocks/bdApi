<?php

class bdApi_ControllerHelper_Navigation extends XenForo_ControllerHelper_Abstract
{
    public function prepareElements(array $nodesGrouped, $expectedParentNodeId = null)
    {
        $elements = array();

        $forumIds = array();
        $forums = array();
        foreach ($nodesGrouped as $parentNodeId => $nodes) {
            foreach ($nodes as $node) {
                if ($node['node_type_id'] === 'Forum') {
                    $forumIds[] = $node['node_id'];
                }
            }
        }

        if (!empty($forumIds)) {
            $forums = $this->_getForumModel()->getForumsByIds(
                $forumIds,
                $this->_getForumModel()->getFetchOptionsToPrepareApiData()
            );
        }

        $arrangeOptions = array(
            'expectedParentNodeId' => $expectedParentNodeId,
            'forums' => $forums,
        );
        $this->_arrangeElements(
            $elements,
            $nodesGrouped,
            is_int($expectedParentNodeId) ? $expectedParentNodeId : 0,
            $arrangeOptions
        );

        return $elements;
    }

    protected function _arrangeElements(
        array &$elements,
        array &$nodesGrouped,
        $parentNodeId,
        array &$options = array()
    ) {
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

                        $element['links']['sub-elements'] = bdApi_Data_Helper_Core::safeBuildApiLink(
                            'navigation',
                            '',
                            array('parent' => $element['navigation_id'])
                        );
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
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->_controller->getModelFromCache('XenForo_Model_Category');
    }

    /**
     * @return bdApi_Extend_Model_Forum
     */
    protected function _getForumModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->_controller->getModelFromCache('XenForo_Model_Forum');
    }


    /**
     * @return bdApi_Extend_Model_LinkForum
     */
    protected function _getLinkForumModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->_controller->getModelFromCache('XenForo_Model_LinkForum');
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->_controller->getModelFromCache('XenForo_Model_Node');
    }

    /**
     * @return bdApi_Extend_Model_Page
     */
    protected function _getPageModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->_controller->getModelFromCache('XenForo_Model_Page');
    }
}
