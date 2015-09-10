<?php

class bdApi_XenForo_Model_Forum extends XFCP_bdApi_XenForo_Model_Forum
{
    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        $fetchOptions['watchUserId'] = XenForo_Visitor::getUserId();
        $fetchOptions['permissionCombinationId'] = XenForo_Visitor::getInstance()->get('permission_combination_id');

        return $fetchOptions;
    }

    public function prepareApiDataForForums(array $forums)
    {
        $data = array();

        foreach ($forums as $key => $forum) {
            $data[] = $this->prepareApiDataForForum($forum);
        }

        return $data;
    }

    public function prepareApiDataForForum(array $forum)
    {
        if (!empty($forum['node_permission_cache'])) {
            XenForo_Visitor::getInstance()->setNodePermissions($forum['node_id'], $forum['node_permission_cache']);
        }

        $publicKeys = array(
            // xf_node
            'node_id' => 'forum_id',
            'title' => 'forum_title',
            'description' => 'forum_description',

            // xf_forum
            'discussion_count' => 'forum_thread_count',
            'message_count' => 'forum_post_count',
        );

        $data = bdApi_Data_Helper_Core::filter($forum, $publicKeys);

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('forums', $forum),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('forums', $forum),
            'sub-categories' => bdApi_Data_Helper_Core::safeBuildApiLink('categories', array(), array('parent_forum_id' => $forum['node_id'])),
            'sub-forums' => bdApi_Data_Helper_Core::safeBuildApiLink('forums', array(), array('parent_forum_id' => $forum['node_id'])),
            'threads' => bdApi_Data_Helper_Core::safeBuildApiLink('threads', array(), array('forum_id' => $forum['node_id'])),
        );

        $data['permissions'] = array(
            'view' => $this->canViewForum($forum),
            'edit' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
            'delete' => XenForo_Visitor::getInstance()->hasAdminPermission('node'),
            'create_thread' => $this->canPostThreadInForum($forum),
            'upload_attachment' => $this->canUploadAndManageAttachment($forum),
        );

        if (XenForo_Application::$versionId > 1050000) {
            /** @var XenForo_Model_Thread $threadModel */
            $threadModel = $this->getModelFromCache('XenForo_Model_Thread');
            $data['permissions']['tag_thread'] = $threadModel->canEditTags(null, $forum);
        }

        if (XenForo_Application::$versionId >= 1020000) {
            $data['forum_is_followed'] = !empty($forum['forum_is_watched']);
            $data['links']['followers'] = bdApi_Data_Helper_Core::safeBuildApiLink('forums/followers', $forum);
            $data['permissions']['follow'] = $this->canWatchForum($forum);
        }

        return $data;
    }

}
