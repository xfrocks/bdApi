<?php

class bdApi_Extend_Model_LinkForum extends XFCP_bdApi_Extend_Model_LinkForum
{
    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        return $fetchOptions;
    }

    public function prepareApiDataForLinkForums(array $linkForums)
    {
        $data = array();

        foreach ($linkForums as $key => $linkForum) {
            $data[] = $this->prepareApiDataForLinkForum($linkForum);
        }

        return $data;
    }

    public function prepareApiDataForLinkForum(array $linkForum)
    {
        $publicKeys = array(
            // xf_node
            'node_id' => 'link_id',
            'title' => 'link_title',
            'description' => 'link_description',
        );

        $data = bdApi_Data_Helper_Core::filter($linkForum, $publicKeys);

        if (!empty($linkForum['link_url'])) {
            $linkUrl = $linkForum['link_url'];
        } else {
            $linkUrl = XenForo_Link::buildPublicLink('link-forums', $linkForum);
        }

        $data['links']['target'] = $linkUrl;

        $data['permissions'] = array(
            'view' => $this->canViewLinkForum($linkForum),
            'edit' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
            'delete' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
        );

        return $data;
    }
}
