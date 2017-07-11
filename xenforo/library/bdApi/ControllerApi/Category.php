<?php

class bdApi_ControllerApi_Category extends bdApi_ControllerApi_Node
{
    protected function _getControllerName()
    {
        return 'bdApi_ControllerApi_Category';
    }

    protected function _getNameSingular()
    {
        return 'category';
    }

    protected function _getNamePlural()
    {
        return 'categories';
    }

    protected function _getAll($parentNodeId = false)
    {
        $nodes = $this->_getNodeModel()->getViewableNodeList();

        $categories = array();
        foreach ($nodes as $node) {
            if ($parentNodeId !== false AND $node['parent_node_id'] != $parentNodeId) {
                continue;
            }

            if ($node['node_type_id'] === 'Category') {
                $categories[] = $node;
            }
        }

        return $categories;
    }

    protected function _getSingle($nodeId)
    {
        $node = $this->_getNodeModel()->getNodeById($nodeId);

        if (!empty($node) AND $node['node_type_id'] !== 'Category') {
            // node exists but not a category
            return false;
        }

        return $node;
    }

    protected function _isViewable(array $category)
    {
        return $this->_getCategoryModel()->canViewCategory($category);
    }

    protected function _prepareApiDataForNodes(array $categories)
    {
        return $this->_getCategoryModel()->prepareApiDataForCategories($categories);
    }

    protected function _prepareApiDataForNode(array $category)
    {
        return $this->_getCategoryModel()->prepareApiDataForCategory($category);
    }

    protected function _responseErrorNotFound()
    {
        return $this->responseError(new XenForo_Phrase('requested_category_not_found'), 404);
    }

    /**
     * @return bdApi_Extend_Model_Category
     */
    protected function _getCategoryModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Category');
    }
}
