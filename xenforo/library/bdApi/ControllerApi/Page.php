<?php

class bdApi_ControllerApi_Page extends bdApi_ControllerApi_Node
{
    public function actionSingle()
    {
        $response = parent::actionSingle();

        if ($response instanceof XenForo_ControllerResponse_View
            && !empty($response->params['page'])
            && !empty($response->params['_node'])
        ) {
            $page = $response->params['_node'];
            $response->viewName = 'bdApi_ViewApi_Page_Single';
            $response->params['_pageTemplateTitle'] = $this->_getPageModel()->getTemplateTitle($page);

            if (!empty($page['log_visits'])) {
                $this->_getPageModel()->logVisit($page, XenForo_Visitor::getInstance()->toArray(), XenForo_Application::$time);
                $response->params['page']['page_view_count']++;
            }
        }

        return $response;
    }


    protected function _getControllerName()
    {
        return 'bdApi_ControllerApi_Page';
    }

    protected function _getNameSingular()
    {
        return 'page';
    }

    protected function _getNamePlural()
    {
        return 'pages';
    }

    protected function _getAll($parentNodeId = false)
    {
        $nodes = $this->_getNodeModel()->getViewableNodeList();

        $pages = array();
        foreach ($nodes as $node) {
            if ($parentNodeId !== false
                && $node['parent_node_id'] != $parentNodeId
            ) {
                continue;
            }

            if ($node['node_type_id'] === 'Page') {
                $pages[] = $node;
            }
        }

        return $pages;
    }

    protected function _getSingle($nodeId)
    {
        return $this->_getPageModel()->getPageById($nodeId);
    }

    protected function _isViewable(array $page)
    {
        return $this->_getPageModel()->canViewPage($page);
    }

    protected function _prepareApiDataForNodes(array $pages)
    {
        return $this->_getPageModel()->prepareApiDataForPages($pages);
    }

    protected function _prepareApiDataForNode(array $page)
    {
        return $this->_getPageModel()->prepareApiDataForPage($page);
    }

    protected function _responseErrorNotFound()
    {
        return $this->responseError(new XenForo_Phrase('requested_category_not_found'), 404);
    }

    /**
     * @return bdApi_XenForo_Model_Page
     */
    protected function _getPageModel()
    {
        return $this->getModelFromCache('XenForo_Model_Page');
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        switch ($action) {
            case 'Single':
                $controllerName = 'XenForo_ControllerPublic_Page';

                $nodeId = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
                if (!empty($nodeId)) {
                    $page = $this->_getPageModel()->getPageById($nodeId);
                    if (!empty($page)) {
                        $params['node_name'] = $page['node_name'];
                    }
                }

                break;
            default:
                parent::_prepareSessionActivityForApi($controllerName, $action, $params);
        }
    }
}
