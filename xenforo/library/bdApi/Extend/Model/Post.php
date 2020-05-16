<?php

class bdApi_Extend_Model_Post extends XFCP_bdApi_Extend_Model_Post
{
    const CONDITIONS_POST_ID = 'bdApi_postId';
    const FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE = 'bdApi_postsInThread_orderReverse';
    const FETCH_OPTIONS_POSTS_IN_THREAD_REPLY_COUNT = 'bdApi_postsInThread_replyCount';
    const FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE_DEFAULT = -1;

    protected $_bdApi_postsInThread_orderReverse = self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE_DEFAULT;

    public function bdApi_getLatestPostId()
    {
        return $this->_getDb()->fetchOne('
            SELECT post_id
            FROM xf_post
            ORDER BY post_id DESC
            LIMIT 1
        ');
    }

    public function bdApi_getPosts(array $conditions = array(), array $fetchOptions = array())
    {
        $stateLimit = $this->prepareStateLimitFromConditions($fetchOptions, 'post');
        $postIdCondition = '';
        if (isset($conditions[self::CONDITIONS_POST_ID])) {
            $postIdCondition = 'AND ' .
                $this->getCutOffCondition('post.post_id', $conditions[self::CONDITIONS_POST_ID]);
        }

        $orderClause = $this->bdApi_preparePostOrderOptions($fetchOptions);
        $joinOptions = $this->preparePostJoinOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->fetchAllKeyed($this->limitQueryResults("
			SELECT post.*
				$joinOptions[selectFields]
			FROM xf_post AS post
			    $joinOptions[joinTables]
			WHERE ($stateLimit $postIdCondition)
                $orderClause
		", $limitOptions['limit'], $limitOptions['offset']), 'post_id');
    }

    public function bdApi_preparePostOrderOptions(array $fetchOptions, $defaultOrderSql = '')
    {
        $choices = array('post_date' => 'post.post_date');

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

    public function getPostsInThread($threadId, array $fetchOptions = array())
    {
        if (!empty($fetchOptions[self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE])
            && isset($fetchOptions[self::FETCH_OPTIONS_POSTS_IN_THREAD_REPLY_COUNT])
        ) {
            $this->_bdApi_postsInThread_orderReverse = $fetchOptions[self::FETCH_OPTIONS_POSTS_IN_THREAD_REPLY_COUNT];
        }

        $posts = parent::getPostsInThread($threadId, $fetchOptions);

        if (!empty($fetchOptions[self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE])) {
            $this->_bdApi_postsInThread_orderReverse = self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE_DEFAULT;
        }

        return $posts;
    }

    public function addPositionLimit($table, $limit, $offset = 0, $column = 'position')
    {
        if ($this->_bdApi_postsInThread_orderReverse > self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE_DEFAULT) {
            if ($limit > 0) {
                $columnRef = ($table ? "$table.$column" : $column);
                $rangeUpper = $this->_bdApi_postsInThread_orderReverse - $offset;
                $rangeLower = $rangeUpper - $limit;

                return " AND ($columnRef > " . $rangeLower . " AND $columnRef <= " . $rangeUpper . ') ';
            }
        }

        return parent::addPositionLimit($table, $limit, $offset, $column);
    }

    public function fetchAllKeyed($sql, $key, $bind = array(), $nullPrefix = '')
    {
        if ($this->_bdApi_postsInThread_orderReverse > self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE_DEFAULT) {
            $sql = str_replace(
                'ORDER BY post.position ASC, post.post_date ASC',
                'ORDER BY post.position DESC, post.post_date DESC',
                $sql,
                $count
            );

            if ($count !== 1) {
                throw new XenForo_Exception('Fatal Conflict: Could not change ORDER BY statement');
            }
        }

        return parent::fetchAllKeyed($sql, $key, $bind, $nullPrefix);
    }

    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        $visitor = XenForo_Visitor::getInstance();

        if (empty($fetchOptions['join'])) {
            $fetchOptions['join'] = XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_USER_PROFILE;
        } else {
            $fetchOptions['join'] |= XenForo_Model_Post::FETCH_USER;
            $fetchOptions['join'] |= XenForo_Model_Post::FETCH_USER_PROFILE;
        }

        $fetchOptions['likeUserId'] = $visitor->get('user_id');

        return $fetchOptions;
    }

    public function prepareApiDataForPosts(array $posts, array $thread, array $forum)
    {
        $data = array();

        foreach ($posts as $key => $post) {
            $data[] = $this->prepareApiDataForPost($post, $thread, $forum);
        }

        return $data;
    }

    public function prepareApiDataForPost(array $post, array $thread, array $forum)
    {
        $visitor = XenForo_Visitor::getInstance();
        $session = bdApi_Data_Helper_Core::safeGetSession();
        /* @var $forumModel XenForo_Model_Forum */
        $forumModel = $this->getModelFromCache('XenForo_Model_Forum');

        $hasAdminScope = (!empty($session) && $session->checkScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM));
        $isAdminRequest = ($hasAdminScope && $visitor->hasAdminPermission('thread'));

        $post = $this->preparePost($post, $thread, $forum);

        $attachments = array();
        if (!empty($post['attachments'])) {
            $attachments = $post['attachments'];
        }

        if (!isset($post['messageHtml'])) {
            $bbCodeOptions = array(
                'states' => array(
                    'prepareApiDataForPost' => array(
                        'post' => $post,
                        'thread' => $thread,
                        'forum' => $forum,
                    ),
                    'viewAttachments' => $this->canViewAttachmentOnPost($post, $thread, $forum),
                ),
            );
            $post['messageHtml'] = bdApi_Data_Helper_Message::getHtml($post, $bbCodeOptions);
        }
        if (isset($post['message'])) {
            $post['messagePlainText'] = bdApi_Data_Helper_Message::getPlainText($post['message']);
        }

        if (isset($post['signature'])) {
            $post['signaturePlainText'] = bdApi_Data_Helper_Message::getPlainText($post['signature']);
        }

        $publicKeys = array(
            // xf_post
            'post_id' => 'post_id',
            'thread_id' => 'thread_id',
            'user_id' => 'poster_user_id',
            'username' => 'poster_username',
            'post_date' => 'post_create_date',
            'message' => 'post_body',
            'messageHtml' => 'post_body_html',
            'messagePlainText' => 'post_body_plain_text',
            'signature' => 'signature',
            'signatureHtml' => 'signature_html',
            'signaturePlainText' => 'signature_plain_text',
            'likes' => 'post_like_count',
            'attach_count' => 'post_attachment_count',
            'likeUsers' => 'like_users',
        );

        $data = bdApi_Data_Helper_Core::filter($post, $publicKeys);

        $data['user_is_ignored'] = XenForo_Visitor::getInstance()->isIgnoring($post['user_id']);

        if (isset($post['message_state'])) {
            switch ($post['message_state']) {
                case 'visible':
                    $data['post_is_published'] = true;
                    $data['post_is_deleted'] = false;
                    break;
                case 'moderated':
                    $data['post_is_published'] = false;
                    $data['post_is_deleted'] = false;
                    break;
                case 'deleted':
                    $data['post_is_published'] = false;
                    $data['post_is_deleted'] = true;
                    break;
            }
        }

        if (isset($post['last_edit_date'])) {
            // since XenForo 1.2.0
            if ($post['last_edit_date'] > 0) {
                $data['post_update_date'] = $post['last_edit_date'];
            } else {
                // by default, last_edit_date = 0
                $data['post_update_date'] = $post['post_date'];
            }
        }

        if (isset($thread['first_post_id'])) {
            $data['post_is_first_post'] = $post['post_id'] === $thread['first_post_id'];
        }

        if (isset($post['like_date'])) {
            $data['post_is_liked'] = !empty($post['like_date']);
        }

        $trackPostOrigin = bdApi_Option::get('trackPostOrigin');
        if (!empty($trackPostOrigin)
            && isset($post[$trackPostOrigin])
            && (
                (
                    $session->getOAuthClientId() === $post[$trackPostOrigin]
                    && $post['user_id'] == $visitor->get('user_id')
                )
                || $isAdminRequest
            )
        ) {
            $data['post_origin'] = $post[$trackPostOrigin];
        }

        if (!empty($attachments)) {
            $data['attachments'] = $this->prepareApiDataForAttachments($attachments, $post, $thread, $forum);
        }

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('posts', $post),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('posts', $post),
            'thread' => bdApi_Data_Helper_Core::safeBuildApiLink('threads', $post),
            'poster' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $post),
            'likes' => bdApi_Data_Helper_Core::safeBuildApiLink('posts/likes', $post),
            'report' => bdApi_Data_Helper_Core::safeBuildApiLink('posts/report', $post),
            'attachments' => bdApi_Data_Helper_Core::safeBuildApiLink('posts/attachments', $post),
            'poster_avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array(
                $post,
                'm',
                false,
                true
            )),
        );

        if (!empty($post['attach_count'])) {
            $data['links']['attachments'] = bdApi_Data_Helper_Core::safeBuildApiLink('posts/attachments', $post);
        }

        $data['permissions'] = array(
            'view' => $this->canViewPost($post, $thread, $forum),
            'edit' => $this->canEditPost($post, $thread, $forum),
            'delete' => $this->canDeletePost($post, $thread, $forum),
            'reply' => $this->_getThreadModel()->canReplyToThread($thread, $forum),
            'like' => $this->canLikePost($post, $thread, $forum),
            'report' => $this->canReportPost($post, $thread, $forum),
            'upload_attachment' => $this->canEditPost($post, $thread, $forum)
                && $forumModel->canUploadAndManageAttachment($forum),
        );

        return $data;
    }

    public function prepareApiDataForAttachments(
        array $attachments,
        array $post,
        array $thread,
        array $forum,
        $tempHash = ''
    ) {
        $data = array();

        foreach ($attachments as $key => $attachment) {
            $data[] = $this->prepareApiDataForAttachment($attachment, $post, $thread, $forum, $tempHash);
        }

        return $data;
    }

    public function prepareApiDataForAttachment(
        array $attachment,
        array $post,
        array $thread,
        array $forum,
        $tempHash = ''
    ) {
        /** @var bdApi_Extend_Model_Attachment $attachmentModel */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
        $data = $attachmentModel->prepareApiDataForAttachment($attachment);

        if (!empty($post['post_id'])) {
            $data['post_id'] = $post['post_id'];
            $data['links'] += array(
                'post' => bdApi_Data_Helper_Core::safeBuildApiLink('posts', $post),
            );
        }

        $data['permissions'] = array(
            'view' => !empty($tempHash)
                ? $attachmentModel->canViewAttachment($attachment, $tempHash)
                : $this->canViewAttachmentOnPost($post, $thread, $forum),
            'delete' => !empty($tempHash)
                ? $attachmentModel->canDeleteAttachment($attachment, $tempHash)
                : $this->canEditPost($post, $thread, $forum),
        );

        if (isset($post['messageHtml'])) {
            $data['attachment_is_inserted'] = empty($post['attachments'][$attachment['attachment_id']]);
        }

        return $data;
    }
}
