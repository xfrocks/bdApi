<?php

class bdApi_XenForo_Model_Post extends XFCP_bdApi_XenForo_Model_Post
{
    const FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE = 'bdApi_postsInThread_orderReverse';

    protected $_bdApi_postsInThread_orderReverse = false;

    public function getPostsInThread($threadId, array $fetchOptions = array())
    {
        if (!empty($fetchOptions[self::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE])) {
            $this->_bdApi_postsInThread_orderReverse = true;
        }

        return parent::getPostsInThread($threadId, $fetchOptions);
    }

    public function fetchAllKeyed($sql, $key, $bind = array(), $nullPrefix = '')
    {
        if ($this->_bdApi_postsInThread_orderReverse) {
            $sql = str_replace('ORDER BY post.position ASC, post.post_date ASC', 'ORDER BY post.position DESC, post.post_date DESC', $sql, $count);

            if (empty($count)) {
                throw new XenForo_Exception('Fatal Conflict: Could not change ORDER BY statement');
            }

            // reset the flag
            $this->_bdApi_postsInThread_orderReverse = false;
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
        /* @var $forumModel XenForo_Model_Forum */
        $forumModel = $this->getModelFromCache('XenForo_Model_Forum');

        $post = $this->preparePost($post, $thread, $forum);

        $attachments = array();
        if (!empty($post['attachments'])) {
            $attachments = $post['attachments'];
        }

        if (!isset($post['messageHtml'])) {
            $post['messageHtml'] = bdApi_Data_Helper_Message::getHtml($post);
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

        if (isset($post['position'])) {
            $data['post_is_first_post'] = (intval($post['position']) === 0);
        }

        if (isset($post['like_date'])) {
            $data['post_is_liked'] = !empty($post['like_date']);
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
            'upload_attachment' => $this->canEditPost($post, $thread, $forum) AND $forumModel->canUploadAndManageAttachment($forum),
        );

        return $data;
    }

    public function prepareApiDataForAttachments(array $attachments, array $post, array $thread, array $forum, $tempHash = '')
    {
        $data = array();

        foreach ($attachments as $key => $attachment) {
            $data[] = $this->prepareApiDataForAttachment($attachment, $post, $thread, $forum, $tempHash);
        }

        return $data;
    }

    public function prepareApiDataForAttachment(array $attachment, array $post, array $thread, array $forum, $tempHash = '')
    {
        /* @var $attachmentModel XenForo_Model_Attachment */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
        $attachment = $attachmentModel->prepareAttachment($attachment);

        $publicKeys = array(
            // xf_attachment
            'attachment_id' => 'attachment_id',
            'content_id' => 'post_id',
            'view_count' => 'attachment_download_count',
            // xf_attachment_data
            'filename' => 'filename',
        );

        $data = bdApi_Data_Helper_Core::filter($attachment, $publicKeys);

        $paths = XenForo_Application::get('requestPaths');
        $paths['fullBasePath'] = XenForo_Application::getOptions()->get('boardUrl') . '/';

        $data['links'] = array('permalink' => XenForo_Link::buildPublicLink('attachments', $attachment));

        if (!empty($attachment['thumbnailUrl'])) {
            $data['links']['thumbnail'] = XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true, $paths);
        }

        if (!empty($post['post_id'])) {
            $data['links'] += array(
                'data' => bdApi_Data_Helper_Core::safeBuildApiLink('posts/attachments', $post, array('attachment_id' => $attachment['attachment_id'])),
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
