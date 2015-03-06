<?php

class bdApi_ControllerApi_Thread extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        if (!empty($threadId)) {
            return $this->responseReroute(__CLASS__, 'get-single');
        }

        $forumIdInput = $this->_input->filterSingle('forum_id', XenForo_Input::STRING);
        if (strlen($forumIdInput) === 0) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_requires_forum_id'), 400);
        }
        $forumIdInput = explode(',', $forumIdInput);
        $forumIdInput = array_map('intval', $forumIdInput);

        $forumIdArray = array();
        $viewableNodes = $this->_getNodeModel()->getViewableNodeList();
        if (in_array(0, $forumIdInput, true)) {
            // accept 0 as a valid forum id
            // TODO: support `child_forums` param
            $forumIdArray[] = 0;
        }
        foreach ($viewableNodes as $viewableNode) {
            $viewableNode['node_id'] = intval($viewableNode['node_id']);
            if (in_array($viewableNode['node_id'], $forumIdInput, true)) {
                $forumIdArray[] = $viewableNode['node_id'];
            }
        }
        if (empty($forumIdArray)) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_requires_forum_id'), 400);
        }
        $forumIdArray = array_unique($forumIdArray);
        asort($forumIdArray);

        $visitor = XenForo_Visitor::getInstance();
        $nodePermissions = $this->_getNodeModel()->getNodePermissionsForPermissionCombination();
        foreach ($nodePermissions as $nodeId => $permissions) {
            $visitor->setNodePermissions($nodeId, $permissions);
        }

        $sticky = $this->_input->filterSingle('sticky', XenForo_Input::STRING);

        $pageNavParams = array('forum_id' => implode(',', $forumIdArray));
        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $limit = XenForo_Application::get('options')->discussionsPerPage;

        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

        $conditions = array(
            'deleted' => false,
            'moderated' => false,
            'node_id' => $forumIdArray,
            'sticky' => (intval($sticky) > 0),
        );
        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page
        );

        $order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'natural'));
        switch ($order) {
            case 'thread_create_date':
                $fetchOptions['order'] = 'post_date';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_create_date_reverse':
                $fetchOptions['order'] = 'post_date';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_update_date':
                $fetchOptions['order'] = 'last_post_date';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_update_date_reverse':
                $fetchOptions['order'] = 'last_post_date';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_view_count':
                $fetchOptions['order'] = 'view_count';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_view_count_reverse':
                $fetchOptions['order'] = 'view_count';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_post_count':
                $fetchOptions['order'] = 'reply_count';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_post_count_reverse':
                $fetchOptions['order'] = 'reply_count';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
        }

        $threads = $this->_getThreadModel()->getThreads($conditions, $this->_getThreadModel()->getFetchOptionsToPrepareApiData($fetchOptions));

        if (!is_numeric($sticky) && intval($page) <= 1) {
            // mixed mode, put sticky threads on top of result if this is the first page
            // mixed mode is the active mode by default (no `sticky` param)
            // the two other modes related are: sticky mode (`sticky`=1) and non-sticky mode (`sticky`=0)
            $stickyThreads = $this->_getThreadModel()->getThreads(
                array_merge($conditions, array('sticky' => 1)),
                array_merge($fetchOptions, array(
                    'limit' => 0,
                    'page' => 0,
                ))
            );
            $threads = array_merge($stickyThreads, $threads);
        }

        $threadsData = $this->_prepareThreads($threads);

        $total = $this->_getThreadModel()->countThreads($conditions);

        $data = array(
            'threads' => $this->_filterDataMany($threadsData),
            'threads_total' => $total,
        );

        if (!$this->_isFieldExcluded('forum') AND count($forumIdArray) == 1) {
            $forumModel = $this->_getForumModel();
            $forum = $forumModel->getForumById(reset($forumIdArray), $forumModel->getFetchOptionsToPrepareApiData());
            $data['forum'] = $this->_filterDataSingle($forumModel->prepareApiDataForForum($forum), array('forum'));
        }

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'threads', array(), $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Thread_List', $data);
    }

    public function actionGetSingle()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId, $this->_getThreadModel()->getFetchOptionsToPrepareApiData(), $this->_getForumModel()->getFetchOptionsToPrepareApiData());

        $firstPost = array();
        if (!$this->_isFieldExcluded('first_post')) {
            $firstPost = $this->_getPostModel()->getPostById($thread['first_post_id'], $this->_getPostModel()->getFetchOptionsToPrepareApiData());

            if (!$this->_isFieldExcluded('first_post.attachments')) {
                $firstPosts = array($firstPost['post_id'] => $firstPost);
                $firstPosts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($firstPosts);
                $firstPost = reset($firstPosts);
            }
        }

        $data = array('thread' => $this->_filterDataSingle($this->_getThreadModel()->prepareApiDataForThread($thread, $forum, $firstPost)));

        return $this->responseData('bdApi_ViewApi_Thread_Single', $data);
    }

    public function actionPostIndex()
    {
        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
        $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($forumId);

        $visitor = XenForo_Visitor::getInstance();

        if (!$this->_getForumModel()->canPostThreadInForum($forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        // the routine is very similar to XenForo_ControllerPublic_Forum::actionAddThread
        $input = $this->_input->filter(array('thread_title' => XenForo_Input::STRING));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['post_body'] = $editorHelper->getMessageText('post_body', $this->_input);
        $input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

        /* @var $writer XenForo_DataWriter_Discussion_Thread */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');

        // note: assumes that the message dw will pick up the username issues
        $writer->bulkSet(array(
            'user_id' => $visitor['user_id'],
            'username' => $visitor['username'],
            'title' => $input['thread_title'],
            'node_id' => $forum['node_id'],
        ));

        // discussion state changes instead of first message state
        $writer->set('discussion_state', $this->_getPostModel()->getPostInsertMessageState(array(), $forum));

        $postWriter = $writer->getFirstMessageDw();
        $postWriter->set('message', $input['post_body']);
        $postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($forum));
        $postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();
        $clientId = $session->getOAuthClientId();
        if (!empty($clientId)) {
            $postWriter->set('bdapi_origin', $clientId);
        }

        $writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);

        $writer->preSave();

        if (!$writer->hasErrors()) {
            $this->assertNotFlooding('post');
        }

        $writer->save();

        $thread = $writer->getMergedData();

        $this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], array(
            // TODO
            'watch_thread_state' => 0,
            'watch_thread' => 0,
            'watch_thread_email' => 0,
        ));

        $this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);

        $this->_request->setParam('thread_id', $thread['thread_id']);
        return $this->responseReroute(__CLASS__, 'get-single');
    }

    public function actionDeleteIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        $deleteType = 'soft';
        $options = array('reason' => '[bd] API');

        if (!$this->_getThreadModel()->canDeleteThread($thread, $forum, $deleteType, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $this->_getThreadModel()->deleteThread($thread['thread_id'], $deleteType, $options);

        XenForo_Model_Log::logModeratorAction('thread', $thread, 'delete_' . $deleteType, array('reason' => $options['reason']));

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostAttachments()
    {
        $contentData = $this->_input->filter(array('forum_id' => XenForo_Input::UINT));
        if (empty($contentData['forum_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_attachments_requires_forum_id'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        $response = $attachmentHelper->doUpload('file', $hash, 'post', $contentData);

        if ($response instanceof XenForo_ControllerResponse_Abstract) {
            return $response;
        }

        $data = array('attachment' => $this->_getPostModel()->prepareApiDataForAttachment(array('post_id' => 0), $response, $hash));

        return $this->responseData('bdApi_ViewApi_Thread_Attachments', $data);
    }

    public function actionDeleteAttachments()
    {
        $contentData = $this->_input->filter(array('forum_id' => XenForo_Input::UINT));
        if (empty($contentData['forum_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_attachments_requires_forum_id'), 400);
        }

        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        return $attachmentHelper->doDelete($hash, $attachmentId);
    }

    public function actionGetFollowed()
    {
        $threadWatches = $this->_getThreadWatchModel()->getThreadsWatchedByUser(XenForo_Visitor::getUserId(), false);
        $threadsData = array();
        $threads = array();

        if (!empty($threadWatches)) {
            $threadIds = array();
            foreach ($threadWatches as $threadWatch) {
                $threadIds[] = $threadWatch['thread_id'];
            }

            $fetchOptions = $this->_getThreadModel()->getFetchOptionsToPrepareApiData();
            $threads = $this->_getThreadModel()->getThreadsByIds($threadIds, $fetchOptions);
            $threads = $this->_prepareThreads($threads);
        }

        foreach ($threadWatches as $threadWatch) {
            foreach ($threads as &$threadData) {
                if ($threadWatch['thread_id'] == $threadData['thread_id']) {
                    $threadData = $this->_getThreadWatchModel()->prepareApiDataForThreadWatches($threadData, $threadWatch);
                    $threadsData[] = $threadData;
                }
            }
        }

        $data = array('threads' => $this->_filterDataMany($threadsData));

        return $this->responseData('bdApi_ViewApi_Thread_Followed', $data);
    }

    public function actionGetFollowers()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        $users = array();

        if ($this->_getThreadModel()->canWatchThread($thread, $forum)) {
            $visitor = XenForo_Visitor::getInstance();

            /* @var $threadWatchModel bdApi_XenForo_Model_ThreadWatch */
            $threadWatchModel = $this->getModelFromCache('XenForo_Model_ThreadWatch');
            $threadWatch = $threadWatchModel->getUserThreadWatchByThreadId($visitor['user_id'], $thread['thread_id']);

            if (!empty($threadWatch)) {
                $user = array(
                    'user_id' => $visitor['user_id'],
                    'username' => $visitor['username'],
                );

                $user = $threadWatchModel->prepareApiDataForThreadWatches($user, $threadWatch);

                $users[] = $user;
            }
        }

        $data = array('users' => $this->_filterDataMany($users));

        return $this->responseData('bdApi_ViewApi_Thread_Followers', $data);
    }

    public function actionPostFollowers()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        $email = $this->_input->filterSingle('email', XenForo_Input::UINT);

        if (!$this->_getThreadModel()->canWatchThread($thread, $forum)) {
            return $this->responseNoPermission();
        }

        $state = ($email > 0 ? 'watch_email' : 'watch_no_email');
        $this->_getThreadWatchModel()->setThreadWatchState(XenForo_Visitor::getUserId(), $thread['thread_id'], $state);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionDeleteFollowers()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        $this->_getThreadWatchModel()->setThreadWatchState(XenForo_Visitor::getUserId(), $threadId, '');

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetNew()
    {
        $this->_assertRegistrationRequired();

        $visitor = XenForo_Visitor::getInstance();
        $threadModel = $this->_getThreadModel();

        $limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
        if ($limit > 0) {
            $maxResults = min($maxResults, $limit);
        }

        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
        if (empty($forumId)) {
            $threadIds = $threadModel->getUnreadThreadIds($visitor->get('user_id'), array('limit' => $maxResults));
        } else {
            $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($forumId);

            $childNodeIds = array_keys($this->_getNodeModel()->getChildNodesForNodeIds(array($forum['node_id'])));

            $threadIds = $threadModel->bdApi_getUnreadThreadIdsInForum($visitor->get('user_id'), array_merge(array($forum['node_id']), $childNodeIds), array('limit' => $maxResults));
        }

        return $this->_getNewOrRecentResponse($threadIds);
    }

    public function actionGetRecent()
    {
        $threadModel = $this->_getThreadModel();

        $days = $this->_input->filterSingle('days', XenForo_Input::UINT);
        if ($days < 1) {
            $days = max(7, XenForo_Application::get('options')->readMarkingDataLifetime);
        }

        $limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
        if ($limit > 0) {
            $maxResults = min($maxResults, $limit);
        }

        $conditions = array(
            'last_post_date' => array(
                '>',
                XenForo_Application::$time - 86400 * $days
            ),
            'deleted' => false,
            'moderated' => false,
            'find_new' => true,
        );

        $fetchOptions = array(
            'limit' => $maxResults,
            'order' => 'last_post_date',
            'orderDirection' => 'desc',
            'join' => XenForo_Model_Thread::FETCH_FORUM_OPTIONS,
        );

        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
        if (!empty($forumId)) {
            $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($forumId);
            $childNodeIds = array_keys($this->_getNodeModel()->getChildNodesForNodeIds(array($forum['node_id'])));
            $conditions['node_id'] = array_merge(array($forum['node_id']), $childNodeIds);
        }

        $threadIds = array_keys($threadModel->getThreads($conditions, $fetchOptions));

        return $this->_getNewOrRecentResponse($threadIds);
    }

    protected function _prepareThreads(array $threads)
    {
        $forumIds = array();
        foreach ($threads as $thread) {
            $forumIds[$thread['node_id']] = true;
        }
        if (!empty($forumIds)) {
            $forums = $this->_getForumModel()->getForumsByIds(array_keys($forumIds));
        }

        foreach (array_keys($threads) as $threadId) {
            if (!empty($forums[$threads[$threadId]['node_id']])) {
                $threads[$threadId]['forum'] = $forums[$threads[$threadId]['node_id']];
            } else {
                unset($threads[$threadId]);
                continue;
            }

            if (!$this->_getThreadModel()->canViewThread($threads[$threadId], $threads[$threadId]['forum'])) {
                unset($threads[$threadId]);
                continue;
            }
        }

        $firstPostIds = array();
        $firstPosts = array();
        if (!$this->_isFieldExcluded('first_post')) {
            foreach ($threads as $thread) {
                $firstPostIds[] = $thread['first_post_id'];
            }
            $firstPosts = $this->_getPostModel()->getPostsByIds($firstPostIds, $this->_getPostModel()->getFetchOptionsToPrepareApiData());

            if (!$this->_isFieldExcluded('first_post.attachments')) {
                $firstPosts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($firstPosts);
            }
        }

        $threadsData = array();
        foreach (array_keys($threads) as $threadId) {
            $firstPost = array();
            if (isset($firstPosts[$threads[$threadId]['first_post_id']])) {
                $firstPost = $firstPosts[$threads[$threadId]['first_post_id']];
            }

            $threadsData[] = $this->_getThreadModel()->prepareApiDataForThread($threads[$threadId], $threads[$threadId]['forum'], $firstPost);
        }

        return $threadsData;
    }

    protected function _getNewOrRecentResponse(array $threadIds)
    {
        $visitor = XenForo_Visitor::getInstance();
        $threadModel = $this->_getThreadModel();

        $results = array();
        $threads = $threadModel->getThreadsByIds($threadIds, array(
            'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
            'permissionCombinationId' => $visitor['permission_combination_id'],
        ));
        foreach ($threadIds AS $threadId) {
            if (!isset($threads[$threadId]))
                continue;
            $threadRef = &$threads[$threadId];

            $threadRef['permissions'] = XenForo_Permission::unserializePermissions($threadRef['node_permission_cache']);

            if ($threadModel->canViewThreadAndContainer($threadRef, $threadRef, $null, $threadRef['permissions'])) {
                $results[] = array('thread_id' => $threadId);
            }
        }

        $data = array('threads' => $results);

        return $this->responseData('bdApi_ViewApi_Thread_NewOrRecent', $data);
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Node');
    }

    /**
     * @return bdApi_XenForo_Model_Forum
     */
    protected function _getForumModel()
    {
        return $this->getModelFromCache('XenForo_Model_Forum');
    }

    /**
     * @return bdApi_XenForo_Model_Thread
     */
    protected function _getThreadModel()
    {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }

    /**
     * @return bdApi_XenForo_Model_Post
     */
    protected function _getPostModel()
    {
        return $this->getModelFromCache('XenForo_Model_Post');
    }

    /**
     * @return bdApi_XenForo_Model_ThreadWatch
     */
    protected function _getThreadWatchModel()
    {
        return $this->getModelFromCache('XenForo_Model_ThreadWatch');
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

}
