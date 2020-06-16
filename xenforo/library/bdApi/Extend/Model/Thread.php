<?php

class bdApi_Extend_Model_Thread extends XFCP_bdApi_Extend_Model_Thread
{
    const CONDITIONS_THREAD_ID = 'bdApi_threadId';
    const CONDITIONS_TAG_ID = 'bdApi_tagId';
    const FETCH_OPTION_JOIN_TAG_CONTENT = 'bdApi_joinTagContent';

    protected $_bdApi_limitQueryResults_nodeId = false;

    public function bdApi_getLatestThreadId()
    {
        return $this->_getDb()->fetchOne('
            SELECT thread_id
            FROM xf_thread
            ORDER BY thread_id DESC
            LIMIT 1
        ');
    }

    public function bdApi_getLatestPostIds(array $threadIds, $limit = null)
    {
        if (count($threadIds) == 0) {
            return array();
        }

        if ($limit === null) {
            $limit = 3;
        }
        $limit = intval($limit);
        if ($limit === 0) {
            return array();
        }

        $postIds = $this->_getDb()->fetchCol('
            SELECT post_id
            FROM `xf_post` AS post
            INNER JOIN `xf_thread` AS thread
            ON (thread.thread_id = post.thread_id)
            WHERE post.thread_id IN (' . $this->_getDb()->quote($threadIds) . ')
                AND position > IF(thread.reply_count >= ' . $limit . ',
                    thread.reply_count - ' . $limit . ', 0)
        ');

        return $postIds;
    }

    public function prepareThreadConditions(array $conditions, array &$fetchOptions)
    {
        $sqlConditions = array(parent::prepareThreadConditions($conditions, $fetchOptions));

        if (isset($conditions[self::CONDITIONS_THREAD_ID])) {
            $sqlConditions[] = $this->getCutOffCondition('thread.thread_id', $conditions[self::CONDITIONS_THREAD_ID]);
        }

        if (XenForo_Application::$versionId > 1050000
            && isset($conditions[self::CONDITIONS_TAG_ID])
        ) {
            $sqlConditions[] = 'tag_content.tag_id = ' . intval($conditions[self::CONDITIONS_TAG_ID]);
            $fetchOptions[self::FETCH_OPTION_JOIN_TAG_CONTENT] = true;
        }

        if (count($sqlConditions) > 1) {
            return $this->getConditionsForClause($sqlConditions);
        } else {
            return $sqlConditions[0];
        }
    }

    public function prepareThreadFetchOptions(array $fetchOptions)
    {
        $prepared = parent::prepareThreadFetchOptions($fetchOptions);

        if (XenForo_Application::$versionId > 1050000
            && !empty($fetchOptions[self::FETCH_OPTION_JOIN_TAG_CONTENT])
        ) {
            $prepared['joinTables'] .= '
					LEFT JOIN xf_tag_content AS tag_content
						ON (tag_content.content_type = "thread"
						AND tag_content.content_id = thread.thread_id)';
        }

        return $prepared;
    }


    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        if (empty($fetchOptions['join'])) {
            $fetchOptions['join'] = XenForo_Model_Thread::FETCH_USER;
        } else {
            $fetchOptions['join'] |= XenForo_Model_Thread::FETCH_USER;
        }

        $visitorUserId = XenForo_Visitor::getUserId();
        if ($visitorUserId > 0) {
            $fetchOptions['readUserId'] = $visitorUserId;
            $fetchOptions['replyBanUserId'] = $visitorUserId;
            $fetchOptions['watchUserId'] = $visitorUserId;
        }

        return $fetchOptions;
    }

    public function prepareApiDataForThreads(array $threads, array $forum, array $firstPosts)
    {
        $data = array();

        foreach ($threads as $key => $thread) {
            $firstPost = array();
            if (isset($firstPosts[$thread['first_post_id']])) {
                $firstPost = $firstPosts[$thread['first_post_id']];
            }

            $data[] = $this->prepareApiDataForThread($thread, $forum, $firstPost);
        }

        return $data;
    }

    public function prepareApiDataForThread(array $thread, array $forum, array $firstPost)
    {
        $thread = $this->prepareThread($thread, $forum);

        $publicKeys = array(
            // xf_thread
            'thread_id' => 'thread_id',
            'node_id' => 'forum_id',
            'title' => 'thread_title',
            'view_count' => 'thread_view_count',
            'user_id' => 'creator_user_id',
            'username' => 'creator_username',
            'post_date' => 'thread_create_date',
            'last_post_date' => 'thread_update_date',

            // XenForo_Model_Thread::prepareThread
            'isNew' => 'thread_is_new',
        );

        $data = bdApi_Data_Helper_Core::filter($thread, $publicKeys);

        $data['user_is_ignored'] = XenForo_Visitor::getInstance()->isIgnoring($thread['user_id']);

        if (isset($thread['reply_count'])) {
            $data['thread_post_count'] = $thread['reply_count'] + 1;
        }

        if (isset($thread['sticky']) AND isset($thread['discussion_state'])) {
            switch ($thread['discussion_state']) {
                case 'visible':
                    $data['thread_is_published'] = true;
                    $data['thread_is_deleted'] = false;
                    $data['thread_is_sticky'] = empty($thread['sticky']) ? false : true;
                    break;
                case 'moderated':
                    $data['thread_is_published'] = false;
                    $data['thread_is_deleted'] = false;
                    $data['thread_is_sticky'] = false;
                    break;
                case 'deleted':
                    $data['thread_is_published'] = false;
                    $data['thread_is_deleted'] = true;
                    $data['thread_is_sticky'] = false;
                    break;
            }
        }

        $data['thread_is_followed'] = !empty($thread['thread_is_watched']);

        if (!empty($firstPost)) {
            /* @var $postModel bdApi_Extend_Model_Post */
            $postModel = $this->_getPostModel();
            $data['first_post'] = $postModel->prepareApiDataForPost($firstPost, $thread, $forum);
        }

        $data['thread_prefixes'] = array();
        if (!empty($thread['prefix_id'])) {
            /** @var bdApi_Extend_Model_ThreadPrefix $prefixModel */
            $prefixModel = $this->getModelFromCache('XenForo_Model_ThreadPrefix');
            $data['thread_prefixes'][] = $prefixModel->prepareApiDataForPrefix($thread);
        }

        if (XenForo_Application::$versionId > 1050000
            && isset($thread['tags'])
        ) {
            $tags = @unserialize($thread['tags']);

            if (is_array($tags)) {
                /** @var bdApi_Extend_Model_Tag $tagModel */
                $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
                $data['thread_tags'] = $tagModel->prepareApiDataForTags($tags);
            }
        }

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('threads', $thread),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('threads', $thread),
            'followers' => bdApi_Data_Helper_Core::safeBuildApiLink('threads/followers', $thread),
            'forum' => bdApi_Data_Helper_Core::safeBuildApiLink('forums', $thread),
            'posts' => bdApi_Data_Helper_Core::safeBuildApiLink(
                'posts',
                array(),
                array('thread_id' => $thread['thread_id'])
            ),
            'first_poster' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $thread),
            'first_poster_avatar' => XenForo_Template_Helper_Core::callHelper(
                'avatar',
                array($thread, 'm', false, true)
            ),
            'first_post' => bdApi_Data_Helper_Core::safeBuildApiLink(
                'posts',
                array('post_id' => $thread['first_post_id'])
            ),
        );

        if (!empty($thread['haveReadData'])) {
            $data['links']['posts_unread'] = bdApi_Data_Helper_Core::safeBuildApiLink(
                'posts/unread',
                null,
                array('thread_id' => $thread['thread_id'])
            );
        }

        if ($thread['last_post_user_id'] != $thread['user_id']) {
            $data['links']['last_poster'] = bdApi_Data_Helper_Core::safeBuildApiLink('users', array(
                'user_id' => $thread['last_post_user_id'],
                'username' => $thread['last_post_username']
            ));
        }

        if ($thread['last_post_id'] != $thread['first_post_id']) {
            $data['links']['last_post'] = bdApi_Data_Helper_Core::safeBuildApiLink(
                'posts',
                array('post_id' => $thread['last_post_id'])
            );
        }

        if (!empty($thread['discussion_type'])) {
            switch ($thread['discussion_type']) {
                case 'poll':
                    $data['thread_has_poll'] = true;
                    $data['links']['poll'] = bdApi_Data_Helper_Core::safeBuildApiLink(
                        'threads/poll',
                        $thread
                    );
                    break;
            }
        }

        $data['permissions'] = array(
            'view' => $this->canViewThread($thread, $forum),
            'delete' => $this->canDeleteThread($thread, $forum),
            'follow' => $this->canWatchThread($thread, $forum),
            'post' => $this->canReplyToThread($thread, $forum),
            'upload_attachment' => $this->_getForumModel()->canUploadAndManageAttachment($forum),
        );

        if (!empty($firstPost)) {
            $data['permissions']['edit_prefix'] = $this->canEditThread($thread, $forum);
            if (XenForo_Application::$versionId > 1050000) {
                $data['permissions']['edit_tags'] = $this->canEditTags($thread, $forum);
            }
            $data['permissions']['edit_title'] = $this->canEditThreadTitle($thread, $forum);
        }

        return $data;
    }

    public function bdApi_getUnreadThreadIdsInForum($userId, $forumId, array $fetchOptions = array())
    {
        $this->_bdApi_limitQueryResults_nodeId = $forumId;
        $threadIds = $this->getUnreadThreadIds($userId, $fetchOptions);
        $this->_bdApi_limitQueryResults_nodeId = false;

        return $threadIds;
    }

    public function limitQueryResults($query, $limit, $offset = 0)
    {
        if ($this->_bdApi_limitQueryResults_nodeId !== false) {
            // TODO: improve this, it may break some query if the WHERE conditions contain a
            // mix of AND and OR operators
            $replacement = false;

            if (!is_array($this->_bdApi_limitQueryResults_nodeId)) {
                if ($this->_bdApi_limitQueryResults_nodeId > 0) {
                    $replacement = "\nthread.node_id = " . $this->_getDb()->quote($this->_bdApi_limitQueryResults_nodeId) . " AND\n";
                }
            } else {
                if (!empty($this->_bdApi_limitQueryResults_nodeId)) {
                    $replacement = "\nthread.node_id IN (" . $this->_getDb()->quote($this->_bdApi_limitQueryResults_nodeId) . ") AND\n";
                }
            }

            if ($replacement !== false AND preg_match('/\s(WHERE)\s/i', $query, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $query = substr_replace($query, $replacement, $matches[1][1] + strlen($matches[1][0]), 0);
            }
        }

        return parent::limitQueryResults($query, $limit, $offset);
    }
}
