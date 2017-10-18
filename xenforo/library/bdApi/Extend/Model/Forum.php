<?php

class bdApi_Extend_Model_Forum extends XFCP_bdApi_Extend_Model_Forum
{
    const CONDITION_NODE_ID = 'bdApi_nodeId';
    const GET_THREAD_PREFIXES = 'bdApi_getThreadPrefixes';

    public function getForumById($id, array $fetchOptions = array())
    {
        $forums = $this->getForums(array(self::CONDITION_NODE_ID => $id), $fetchOptions);

        return reset($forums);
    }

    public function getForumsByIds(array $forumIds, array $fetchOptions = array())
    {
        return $this->getForums(array(self::CONDITION_NODE_ID => $forumIds), $fetchOptions);
    }

    public function getForums(array $conditions = array(), array $fetchOptions = array())
    {
        $forums = parent::getForums($conditions, $fetchOptions);

        if (!empty($forums)
            && !empty($fetchOptions[self::GET_THREAD_PREFIXES])
        ) {
            /** @var bdApi_Extend_Model_ThreadPrefix $prefixModel */
            $prefixModel = $this->getModelFromCache('XenForo_Model_ThreadPrefix');
            $prefixes = $prefixModel->bdApi_getUsablePrefixesByForums(array_keys($forums));

            foreach ($forums as &$forumRef) {
                $forumRef['prefixes'] = array();

                if (isset($prefixes[$forumRef['node_id']])) {
                    $forumRef['prefixes'] = $prefixes[$forumRef['node_id']];
                }
            }
        }

        return $forums;
    }

    public function prepareForumConditions(array $conditions, array &$fetchOptions)
    {
        $db = $this->_getDb();
        $sqlConditions = array(parent::prepareForumConditions($conditions, $fetchOptions));

        if (isset($conditions[self::CONDITION_NODE_ID])) {
            if (is_array($conditions[self::CONDITION_NODE_ID])) {
                if (count($conditions[self::CONDITION_NODE_ID]) > 0) {
                    $sqlConditions[] = 'forum.node_id IN (' . $db->quote($conditions[self::CONDITION_NODE_ID]) . ')';
                } else {
                    $sqlConditions[] = '1=2';
                }
            } else {
                $sqlConditions[] = 'forum.node_id = ' . $db->quote($conditions[self::CONDITION_NODE_ID]);
            }
        }

        if (count($sqlConditions)) {
            return $this->getConditionsForClause($sqlConditions);
        } else {
            return reset($sqlConditions);
        }
    }

    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        $fetchOptions['watchUserId'] = XenForo_Visitor::getUserId();

        if (!isset($fetchOptions[self::GET_THREAD_PREFIXES])) {
            $fetchOptions[self::GET_THREAD_PREFIXES] = true;
        }

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

        if (isset($forum['prefixes'])) {
            /** @var bdApi_Extend_Model_ThreadPrefix $prefixModel */
            $prefixModel = $this->getModelFromCache('XenForo_Model_ThreadPrefix');
            $data['forum_prefixes'] = $prefixModel->prepareApiDataForPrefixes($forum['prefixes']);
            $data['thread_default_prefix_id'] = $forum['default_prefix_id'];
            $data['thread_prefix_is_required'] = !empty($forum['require_prefix']);
        }

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('forums', $forum),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('forums', $forum),
            'sub-categories' => bdApi_Data_Helper_Core::safeBuildApiLink(
                'categories',
                array(),
                array('parent_forum_id' => $forum['node_id'])
            ),
            'sub-forums' => bdApi_Data_Helper_Core::safeBuildApiLink(
                'forums',
                array(),
                array('parent_forum_id' => $forum['node_id'])
            ),
            'threads' => bdApi_Data_Helper_Core::safeBuildApiLink(
                'threads',
                array(),
                array('forum_id' => $forum['node_id'])
            ),
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
