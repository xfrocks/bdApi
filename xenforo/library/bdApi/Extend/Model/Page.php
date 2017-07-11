<?php

class bdApi_Extend_Model_Page extends XFCP_bdApi_Extend_Model_Page
{
    public function prepareApiDataForPages(array $pages)
    {
        $data = array();

        foreach ($pages as $key => $page) {
            $data[] = $this->prepareApiDataForPage($page);
        }

        return $data;
    }

    public function prepareApiDataForPage(array $page)
    {
        $publicKeys = array(
            // xf_node
            'node_id' => 'page_id',
            'title' => 'page_title',
            'description' => 'page_description',

            // xf_page
            'view_count' => 'page_view_count',
        );

        $data = bdApi_Data_Helper_Core::filter($page, $publicKeys);

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('pages', $page),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('pages', $page),
            'sub-pages' => bdApi_Data_Helper_Core::safeBuildApiLink(
                'pages',
                array(),
                array('parent_page_id' => $page['node_id'])
            ),
        );

        $data['permissions'] = array(
            'view' => $this->canViewPage($page),
            'edit' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
            'delete' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
        );

        return $data;
    }
}
