<?php

class bdApi_ControllerApi_Post extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
        if (!empty($postId)) {
            return $this->responseReroute(__CLASS__, 'single');
        }

        $postIds = $this->_input->filterSingle('post_ids', XenForo_Input::STRING);
        if (!empty($postIds)) {
            return $this->responseReroute(__CLASS__, 'multiple');
        }

        $pageOfPost = null;
        $thread = null;
        $forum = null;

        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        $pageOfPostId = $this->_input->filterSingle('page_of_post_id', XenForo_Input::UINT);
        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        $order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'natural'));

        if ($threadId > 0) {
            list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable(
                $threadId,
                $this->_getThreadModel()->getFetchOptionsToPrepareApiData(),
                $this->_getForumModel()->getFetchOptionsToPrepareApiData()
            );
        }

        if ($pageOfPostId > 0) {
            list($pageOfPost, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable(
                $pageOfPostId,
                array(),
                $this->_getThreadModel()->getFetchOptionsToPrepareApiData(),
                $this->_getForumModel()->getFetchOptionsToPrepareApiData()
            );
        }

        $pageNavParams = array();
        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $limit = XenForo_Application::get('options')->messagesPerPage;

        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

        if (!empty($pageOfPost)) {
            $page = floor($pageOfPost['position'] / $limit) + 1;
        }

        $fetchOptions = array(
            'deleted' => false,
            'moderated' => false,
            'limit' => $limit,
            'page' => $page
        );

        switch ($order) {
            case 'natural_reverse':
                if (empty($thread['thread_id'])) {
                    return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_order_x_requires_thread_id',
                        array('order' => $order)
                    ), 400);
                }

                // load the class to make sure our constant is accessible
                $this->_getPostModel();
                $fetchOptions[bdApi_Extend_Model_Post::FETCH_OPTIONS_POSTS_IN_THREAD_ORDER_REVERSE] = true;
                $fetchOptions[bdApi_Extend_Model_Post::FETCH_OPTIONS_POSTS_IN_THREAD_REPLY_COUNT] = $thread['reply_count'];
                $pageNavParams['order'] = $order;
                break;
            case 'post_create_date':
                if (!empty($thread['thread_id'])) {
                    return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_order_x_no_thread_id',
                        array('order' => $order)
                    ), 400);
                }

                $fetchOptions['order'] = 'post_date';
                $fetchOptions['direction'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'post_create_date_reverse':
                if (!empty($thread['thread_id'])) {
                    return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_order_x_no_thread_id',
                        array('order' => $order)
                    ), 400);
                }

                $fetchOptions['order'] = 'post_date';
                $fetchOptions['direction'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
        }

        if (!empty($thread['thread_id'])) {
            $pageNavParams['thread_id'] = $thread['thread_id'];
            $posts = $this->_getPostModel()->getPostsInThread($thread['thread_id'],
                $this->_getPostModel()->getFetchOptionsToPrepareApiData($fetchOptions));
            $total = $thread['reply_count'] + 1;
        } else {
            $conditions = array();
            $posts = $this->_getPostModel()->bdApi_getPosts(
                $conditions,
                $this->_getPostModel()->getFetchOptionsToPrepareApiData($fetchOptions)
            );
            $total = $this->_getPostModel()->bdApi_countPosts($conditions, $fetchOptions);
        }

        $postsData = $this->_preparePosts($posts, $thread, $forum);

        $data = array(
            'posts' => $this->_filterDataMany($postsData),
            'posts_total' => $total,

            '_thread' => $thread,
        );

        if (!empty($thread['thread_id'])) {
            $maxPostDate = 0;
            foreach ($posts as $post) {
                if ($post['post_date'] > $maxPostDate) {
                    $maxPostDate = $post['post_date'];
                }
            }

            $this->_getThreadModel()->markThreadRead($thread, $forum, $maxPostDate);
            $this->_getThreadModel()->logThreadView($thread['thread_id']);

            if (!$this->_isFieldExcluded('thread')) {
                $data['thread'] = $this->_filterDataSingle($this->_getThreadModel()->prepareApiDataForThread($thread,
                    $forum, array()), array('thread'));
            }
        }

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(),
            $data, $limit, $total, $page, 'posts', array(), $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Post_List', $data);
    }

    public function actionSingle()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
        list($post, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($postId, $this->_getPostModel()->getFetchOptionsToPrepareApiData(), $this->_getThreadModel()->getFetchOptionsToPrepareApiData(), $this->_getForumModel()->getFetchOptionsToPrepareApiData());

        $posts = array($post['post_id'] => $post);
        $postsData = $this->_preparePosts($posts, $thread, $forum);
        $postData = reset($postsData);

        $data = array('post' => $this->_filterDataSingle($postData));

        return $this->responseData('bdApi_ViewApi_Post_Single', $data);
    }

    public function actionMultiple()
    {
        $postIdsInput = $this->_input->filterSingle('post_ids', XenForo_Input::STRING);
        $postIds = array_map('intval', explode(',', $postIdsInput));
        if (empty($postIds)) {
            return $this->responseNoPermission();
        }

        $posts = $this->_getPostModel()->getPostsByIds(
            $postIds,
            $this->_getPostModel()->getFetchOptionsToPrepareApiData()
        );

        $postsOrdered = array();
        foreach ($postIds as $postId) {
            if (isset($posts[$postId])) {
                $postsOrdered[$postId] = $posts[$postId];
            }
        }

        $postsData = $this->_preparePosts($postsOrdered);

        $data = array(
            'posts' => $this->_filterDataMany($postsData),
        );

        return $this->responseData('bdApi_ViewApi_Post_List', $data);
    }

    public function actionPostIndex()
    {
        $input = $this->_input->filter(array(
            'thread_id' => XenForo_Input::UINT,
        ));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['post_body'] = $editorHelper->getMessageText('post_body', $this->_input);
        $input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($input['thread_id']);

        $visitor = XenForo_Visitor::getInstance();

        if (!$this->_getThreadModel()->canReplyToThread($thread, $forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
        $writer->set('user_id', $visitor['user_id']);
        $writer->set('username', $visitor['username']);
        $writer->set('message', $input['post_body']);
        $writer->set('message_state', $this->_getPostModel()->getPostInsertMessageState($thread, $forum));
        $writer->set('thread_id', $thread['thread_id']);
        $writer->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($thread));
        $writer->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();
        $clientId = $session->getOAuthClientId();
        if (!empty($clientId)) {
            $writer->set('bdapi_origin', $clientId);
        }

        switch ($this->_spamCheck(array(
            'content_type' => 'post',
            'content' => $input['post_body'],
            'permalink' => XenForo_Link::buildPublicLink('canonical:threads', $thread),
        ))) {
            case self::SPAM_RESULT_MODERATED:
            case self::SPAM_RESULT_DENIED;
                return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                break;
        }

        $writer->preSave();

        if ($writer->hasErrors()) {
            return $this->responseErrors($writer->getErrors(), 400);
        }

        $this->assertNotFlooding('post');

        $writer->save();
        $post = $writer->getMergedData();

        $this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], array(
            // TODO
            'watch_thread_state' => 0,
            'watch_thread' => 0,
            'watch_thread_email' => 0,
        ));

        $this->_request->setParam('post_id', $post['post_id']);
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionPutIndex()
    {
        $input = $this->_input->filter(array(
            'post_id' => XenForo_Input::UINT,
            'thread_title' => XenForo_Input::STRING,
            'thread_prefix_id' => XenForo_Input::STRING,
            'thread_tags' => XenForo_Input::STRING,
        ));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['post_body'] = $editorHelper->getMessageText('post_body', $this->_input);
        $input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

        list($post, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($input['post_id']);

        if (!$this->_getPostModel()->canEditPost($post, $thread, $forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
        $dw->setExistingData($post, true);

        if ($this->_input->inRequest('post_body')
            || $this->_input->inRequest('post_body_html')
        ) {
            $dw->set('message', $input['post_body']);
        }

        switch ($post['message_state']) {
            case 'deleted':
                if ($this->_getPostModel()->canUndeletePost($post, $thread, $forum)) {
                    $dw->set('message_state', 'visible');
                }
                break;
            case 'moderated':
                if ($this->_getPostModel()->canApproveUnapprovePost($post, $thread, $forum)) {
                    $dw->set('message_state', 'visible');
                }
                break;
        }

        $dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($post));
        $dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

        switch ($this->_spamCheck(array(
            'content_type' => 'post',
            'content_id' => $post['post_id'],
            'content' => $input['post_body'],
            'permalink' => XenForo_Link::buildPublicLink('canonical:threads', $thread),
        ))) {
            case self::SPAM_RESULT_MODERATED:
            case self::SPAM_RESULT_DENIED;
                return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                break;
        }

        $dw->preSave();

        if ($dw->hasErrors()) {
            return $this->responseErrors($dw->getErrors(), 400);
        }

        $threadDw = null;
        $threadTagger = null;
        if ($post['post_id'] == $thread['first_post_id']
            && $this->_getThreadModel()->canEditThread($thread, $forum)
        ) {
            $threadDw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
            $threadDw->setExistingData($thread, true);

            if ($this->_input->inRequest('thread_title')) {
                $threadDw->set('title', $input['thread_title']);
            }

            if ($this->_input->inRequest('thread_prefix_id')) {
                $prefixId = $input['thread_prefix_id'];
                if (!is_numeric($prefixId)
                    && !empty($forum['default_prefix_id'])
                ) {
                    $prefixId = $forum['default_prefix_id'];
                }
                $threadDw->set('prefix_id', $prefixId);
            }

            if (XenForo_Application::$versionId > 1050000
                && $this->_input->inRequest('thread_tags')
                && $this->_getThreadModel()->canEditTags($thread, $forum)
            ) {
                // thread tagging is available since XenForo 1.5.0
                /** @var XenForo_Model_Tag $tagModel */
                $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
                $threadTagger = $tagModel->getTagger('thread');
                $threadTagger->setContent($thread['thread_id'])->setPermissionsFromContext($thread, $forum);
                $threadTagger->setTags($tagModel->splitTags($input['thread_tags']));
                $threadDw->mergeErrors($threadTagger->getErrors());
            }

            $threadDw->preSave();

            if (!$threadDw->get('prefix_id')
                && !empty($forum['require_prefix'])
            ) {
                /** @var bdApi_Extend_Model_ThreadPrefix $prefixModel */
                $prefixModel = $this->getModelFromCache('XenForo_Model_ThreadPrefix');
                if ($prefixModel->getUsablePrefixesInForums($forum['node_id'])) {
                    $threadDw->error(new XenForo_Phrase('please_select_a_prefix'), 'prefix_id');
                }
            }

            if ($threadDw->hasErrors()) {
                return $this->responseErrors($threadDw->getErrors(), 400);
            }
        }

        XenForo_Db::beginTransaction();

        $dw->save();

        if ($threadDw != null
            && $threadDw->hasChanges()
        ) {
            $threadDw->save();
        }

        if ($threadTagger != null) {
            $threadTagger->save();
        }

        XenForo_Db::commit();

        if (XenForo_Application::isRegistered('bdApi_responseReroute')) {
            return call_user_func_array(array($this, 'responseReroute'),
                XenForo_Application::get('bdApi_responseReroute'));
        }

        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionDeleteIndex()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

        /** @var bdApi_ControllerHelper_Delete $deleteHelper */
        $deleteHelper = $this->getHelper('bdApi_ControllerHelper_Delete');
        $reason = $deleteHelper->filterReason();

        list($post, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($postId);

        $deleteType = 'soft';
        $options = array('reason' => $reason);

        if (!$this->_getPostModel()->canDeletePost($post, $thread, $forum, $deleteType, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $this->_getPostModel()->deletePost($postId, $deleteType, $options, $forum);

        if ($post['post_id'] == $thread['first_post_id']) {
            XenForo_Model_Log::logModeratorAction('thread', $thread,
                'delete_' . $deleteType, array('reason' => $reason));
        } else {
            XenForo_Model_Log::logModeratorAction('post', $post,
                'delete_' . $deleteType, array('reason' => $reason), $thread);
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetLikes()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

        /* @var $ftpHelper XenForo_ControllerHelper_ForumThreadPost */
        $ftpHelper = $this->getHelper('ForumThreadPost');
        list($post, ,) = $ftpHelper->assertPostValidAndViewable($postId);

        $likes = $this->_getLikeModel()->getContentLikes('post', $post['post_id']);
        $users = array();

        if (!empty($likes)) {
            foreach ($likes as $like) {
                $users[] = array(
                    'user_id' => $like['like_user_id'],
                    'username' => $like['username'],
                );
            }
        }

        $data = array('users' => $users);

        return $this->responseData('bdApi_ViewApi_Post_Likes', $data);
    }

    public function actionPostLikes()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
        list($post, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($postId);

        if (!$this->_getPostModel()->canLikePost($post, $thread, $forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $likeModel = $this->_getLikeModel();

        $existingLike = $likeModel->getContentLikeByLikeUser('post', $postId, XenForo_Visitor::getUserId());
        if (empty($existingLike)) {
            $latestUsers = $likeModel->likeContent('post', $postId, $post['user_id']);

            if ($latestUsers === false) {
                return $this->responseNoPermission();
            }
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionDeleteLikes()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
        list($post, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($postId);

        if (!$this->_getPostModel()->canLikePost($post, $thread, $forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $likeModel = $this->_getLikeModel();

        $existingLike = $likeModel->getContentLikeByLikeUser('post', $postId, XenForo_Visitor::getUserId());
        if (!empty($existingLike)) {
            $latestUsers = $likeModel->unlikeContent($existingLike);

            if ($latestUsers === false) {
                return $this->responseNoPermission();
            }
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetAttachments()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);

        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
        if (!empty($attachmentId)) {
            return $this->responseReroute('bdApi_ControllerApi_Attachment',  'get-data');
        }

        list($post, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($postId, $this->_getPostModel()->getFetchOptionsToPrepareApiData());

        $posts = array($post['post_id'] => $post);
        $posts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($posts);
        $posts = $this->_getPostModel()->prepareApiDataForPosts($posts, $thread, $forum);

        $post = reset($posts);
        $attachments = isset($post['attachments']) ? $post['attachments'] : array();

        $data = array('attachments' => $this->_filterDataMany($attachments));

        return $this->responseData('bdApi_ViewApi_Post_Attachments', $data);
    }

    public function actionPostAttachments()
    {
        $contentData = $this->_input->filter(array(
            'post_id' => XenForo_Input::UINT,
            'thread_id' => XenForo_Input::UINT,
        ));
        if (empty($contentData['post_id']) AND empty($contentData['thread_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_attachments_requires_ids'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        $response = $attachmentHelper->doUpload('file', $hash, 'post', $contentData);

        if ($response instanceof XenForo_ControllerResponse_Abstract) {
            return $response;
        }

        $data = array('attachment' => $this->_filterDataSingle(
            $this->_getPostModel()->prepareApiDataForAttachment(
                $response, $contentData, $contentData, $contentData, $hash)));

        return $this->responseData('bdApi_ViewApi_Post_Attachments', $data);
    }

    public function actionDeleteAttachments()
    {
        $contentData = $this->_input->filter(array(
            'post_id' => XenForo_Input::UINT,
            'thread_id' => XenForo_Input::UINT,
        ));
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

        if (empty($contentData['post_id']) AND empty($contentData['thread_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_posts_attachments_requires_ids'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        return $attachmentHelper->doDelete($hash, $attachmentId);
    }

    public function actionPostReport()
    {
        $postId = $this->_input->filterSingle('post_id', XenForo_Input::UINT);
        $message = $this->_input->filterSingle('message', XenForo_Input::STRING);

        list($post, $thread, $forum) = $this->_getForumThreadPostHelper()->assertPostValidAndViewable($postId);

        if (!$this->_getPostModel()->canReportPost($post, $thread, $forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        if (!$message) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_x_report_requires_message', array('route' => 'posts')), 400);
        }

        $this->assertNotFlooding('report');

        /* @var $reportModel XenForo_Model_Report */
        $reportModel = $this->getModelFromCache('XenForo_Model_Report');
        $reportModel->reportContent('post', $post, $message);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    protected function _preparePosts(array $posts, array $thread = null, array $forum = null)
    {
        // check for $thread being null because we only prepare `post`.`thread` for request
        // of multiple posts from different threads (likely from actionMultiple)
        $preparePostThread = (!$this->_isFieldExcluded('thread') && $thread === null);

        $threads = array();
        if ($thread !== null) {
            $threads[$thread['thread_id']] = $thread;
        }

        $forums = array();
        if ($forum !== null) {
            $forums[$forum['node_id']] = $forum;
        }

        $threadIds = array();
        foreach ($posts as $post) {
            if (!isset($threads[$post['thread_id']])) {
                $threadIds[] = $post['thread_id'];
            }
        }
        if (!empty($threadIds)) {
            $threads += $this->_getThreadModel()->getThreadsByIds(
                $threadIds,
                $this->_getThreadModel()->getFetchOptionsToPrepareApiData()
            );
        }

        $forumIds = array();
        foreach ($threads as $thread) {
            if (!isset($forums[$thread['node_id']])) {
                $forumIds[] = $thread['node_id'];
            }
        }
        if (!empty($forumIds)) {
            $forums += $this->_getForumModel()->getForumsByIds($forumIds);
        }

        if (!$this->_isFieldExcluded('attachments')) {
            $posts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($posts);
        }

        $visitor = XenForo_Visitor::getInstance();
        $nodePermissions = $this->_getNodeModel()->getNodePermissionsForPermissionCombination();
        foreach ($nodePermissions as $nodeId => $permissions) {
            $visitor->setNodePermissions($nodeId, $permissions);
        }

        $postsData = array();
        foreach ($posts as &$postRef) {
            if (!isset($threads[$postRef['thread_id']])) {
                continue;
            }
            $threadRef = &$threads[$postRef['thread_id']];

            if (!isset($forums[$threadRef['node_id']])) {
                continue;
            }
            $forumRef = &$forums[$threadRef['node_id']];

            if (!$this->_getPostModel()->canViewPostAndContainer($postRef, $threadRef, $forumRef)) {
                continue;
            }

            $postData = $this->_getPostModel()->prepareApiDataForPost($postRef, $threadRef, $forumRef);

            if ($preparePostThread) {
                $postData['thread'] = $this->_getThreadModel()
                    ->prepareApiDataForThread($threadRef, $forumRef, array());
            }

            $postsData[] = $postData;
        }

        return $postsData;
    }

    /**
     * @return bdApi_Extend_Model_Post
     */
    protected function _getPostModel()
    {
        return $this->getModelFromCache('XenForo_Model_Post');
    }

    /**
     * @return bdApi_Extend_Model_Thread
     */
    protected function _getThreadModel()
    {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }

    /**
     * @return bdApi_Extend_Model_Forum
     */
    protected function _getForumModel()
    {
        return $this->getModelFromCache('XenForo_Model_Forum');
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Node');
    }

    /**
     * @return bdApi_Extend_Model_ThreadWatch
     */
    protected function _getThreadWatchModel()
    {
        return $this->getModelFromCache('XenForo_Model_ThreadWatch');
    }

    /**
     * @return XenForo_Model_Like
     */
    protected function _getLikeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Like');
    }

    /**
     * @return XenForo_ControllerHelper_ForumThreadPost
     */
    protected function _getForumThreadPostHelper()
    {
        return $this->getHelper('ForumThreadPost');
    }

    /**
     * @return bdApi_ControllerHelper_Attachment
     */
    protected function _getAttachmentHelper()
    {
        return $this->getHelper('bdApi_ControllerHelper_Attachment');
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        if ($action === 'GetIndex') {
            $threadId = $this->_request->getParam('thread_id');
            if (!empty($threadId)) {
                $controllerName = 'XenForo_ControllerPublic_Thread';
                $params['thread_id'] = $threadId;
                return;
            }
        }

        $controllerName = 'XenForo_ControllerPublic_Post';
    }
}
