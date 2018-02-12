<?php

class bdApi_ControllerApi_Thread extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        if (!empty($threadId)) {
            return $this->responseReroute(__CLASS__, 'single');
        }

        $threadIds = $this->_input->filterSingle('thread_ids', XenForo_Input::STRING);
        if (!empty($threadIds)) {
            return $this->responseReroute(__CLASS__, 'multiple');
        }

        $forumIdInput = $this->_input->filterSingle('forum_id', XenForo_Input::STRING);
        $creatorUserId = $this->_input->filterSingle('creator_user_id', XenForo_Input::UINT);
        $threadPrefixId = $this->_input->filterSingle('thread_prefix_id', XenForo_Input::UINT);
        $threadTagId = $this->_input->filterSingle('thread_tag_id', XenForo_Input::UINT);
        $sticky = $this->_input->filterSingle('sticky', XenForo_Input::STRING);
        $stickyBool = intval($sticky) > 0;
        $order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'natural'));

        $forumIdInput = preg_split('#[^0-9]#', $forumIdInput, -1, PREG_SPLIT_NO_EMPTY);
        $viewableNodes = $this->_getNodeModel()->getViewableNodeList();
        $viewableForums = array_filter($viewableNodes, function ($f) {
            return $f['node_type_id'] === 'Forum';
        });
        $forumIdArray = array_intersect($forumIdInput, array_keys($viewableForums));
        if (count($forumIdArray) !== count($forumIdInput)) {
            return $this->responseError(new XenForo_Phrase('requested_forum_not_found'), 404);
        }
        $forumIdArray = array_unique(array_map('intval', $forumIdArray));
        asort($forumIdArray);

        $theForumId = count($forumIdArray) === 1 ? reset($forumIdArray) : 0;
        $theForum = null;

        $pageNavParams = array();
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $conditions = array(
            'deleted' => false,
            'moderated' => false,
            'sticky' => (intval($sticky) > 0),
        );
        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page
        );
        $total = 0;

        if (!empty($forumIdArray)) {
            $pageNavParams['forum_id'] = implode(',', $forumIdArray);
            $conditions['node_id'] = $forumIdArray;
        }

        if ($theForumId > 0) {
            // forum threads has sticky-mixed mode (see below)
            $conditions['sticky'] = $stickyBool;
        } elseif (is_numeric($sticky)) {
            // otherwise only set the thread condition if found valid value for `sticky`
            $conditions['sticky'] = $stickyBool;
        }

        $creatorUser = null;
        if ($creatorUserId > 0) {
            $creatorUser = $this->_getUserModel()->getUserById(
                $creatorUserId,
                $this->_getUserModel()->getFetchOptionsToPrepareApiData()
            );
            if (empty($creatorUser)
                || !$this->_getUserProfileModel()->canViewFullUserProfile($creatorUser)
            ) {
                return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
            }

            $pageNavParams['creator_user_id'] = $creatorUser['user_id'];
            $conditions['user_id'] = $creatorUser['user_id'];
        }

        if ($threadPrefixId > 0) {
            if ($theForumId <= 0) {
                return $this->responseError(
                    new XenForo_Phrase('bdapi_slash_threads_thread_prefix_id_requires_forum_id'),
                    400
                );
            }

            $pageNavParams['thread_prefix_id'] = $threadPrefixId;
            $conditions['prefix_id'] = $threadPrefixId;
        }

        $threadTag = null;
        if (XenForo_Application::$versionId > 1050000
            && $threadTagId > 0) {
            if ($theForumId <= 0) {
                return $this->responseError(
                    new XenForo_Phrase('bdapi_slash_threads_thread_tag_id_requires_forum_id'),
                    400
                );
            }

            $threadTag = $this->_getTagModel()->getTagById($threadTagId);
            if (empty($threadTag)) {
                return $this->responseError(new XenForo_Phrase('requested_tag_not_found'), 404);
            }

            $pageNavParams['thread_tag_id'] = $threadTag['tag_id'];
            $this->_getThreadModel();
            $conditions[bdApi_Extend_Model_Thread::CONDITIONS_TAG_ID] = $threadTag['tag_id'];
        }

        switch ($order) {
            case 'thread_create_date':
                $fetchOptions['order'] = 'post_date';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;

                if ($theForumId < 1) {
                    // disable paging for this order if not in a forum
                    $fetchOptions['page'] = 0;
                }
                break;
            case 'thread_create_date_reverse':
                $fetchOptions['order'] = 'post_date';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;

                if ($theForumId < 1) {
                    // disable paging for this order if not in a forum
                    $fetchOptions['page'] = 0;
                }
                break;
            case 'thread_update_date':
                $fetchOptions['order'] = 'last_post_date';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;

                if ($theForumId < 1) {
                    // disable paging for this order if not in a forum
                    $fetchOptions['page'] = 0;
                }
                break;
            case 'thread_update_date_reverse':
                $fetchOptions['order'] = 'last_post_date';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;

                if ($theForumId < 1) {
                    // disable paging for this order if not in a forum
                    $fetchOptions['page'] = 0;
                }
                break;
            case 'thread_view_count':
                if ($theForumId <= 0) {
                    return $this->responseError(new XenForo_Phrase(
                        'bdapi_slash_threads_order_x_requires_forum_id',
                        array('order' => $order)
                    ), 400);
                }

                $fetchOptions['order'] = 'view_count';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_view_count_reverse':
                if ($theForumId <= 0) {
                    return $this->responseError(new XenForo_Phrase(
                        'bdapi_slash_threads_order_x_requires_forum_id',
                        array('order' => $order)
                    ), 400);
                }

                $fetchOptions['order'] = 'view_count';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_post_count':
                if ($theForumId <= 0) {
                    return $this->responseError(new XenForo_Phrase(
                        'bdapi_slash_threads_order_x_requires_forum_id',
                        array('order' => $order)
                    ), 400);
                }

                $fetchOptions['order'] = 'reply_count';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_post_count_reverse':
                if ($theForumId <= 0) {
                    return $this->responseError(new XenForo_Phrase(
                        'bdapi_slash_threads_order_x_requires_forum_id',
                        array('order' => $order)
                    ), 400);
                }

                $fetchOptions['order'] = 'reply_count';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            default:
                if ($theForumId > 0) {
                    $fetchOptions['order'] = 'last_post_date';
                    $fetchOptions['orderDirection'] = 'desc';
                } else {
                    $this->_assertValidToken();

                    // manually prepare threads total count for paging
                    $total = $this->_getThreadModel()->bdApi_getLatestThreadId();

                    $threadIdEnd = max(1, $fetchOptions['page']) * $fetchOptions['limit'];
                    $threadIdStart = $threadIdEnd - $fetchOptions['limit'] + 1;
                    $this->_getPostModel();
                    $conditions[bdApi_Extend_Model_Thread::CONDITIONS_THREAD_ID] =
                        array('>=<', $threadIdStart, $threadIdEnd);

                    // paging was done by conditions (see above), remove it from fetch options
                    $fetchOptions['page'] = 0;
                }
                break;
        }

        $fetchOptions = $this->_getThreadModel()->getFetchOptionsToPrepareApiData($fetchOptions);
        $threads = $this->_getThreadModel()->getThreads($conditions, $fetchOptions);

        if ($theForumId > 0
            && !is_numeric($sticky)
            && intval($page) <= 1
        ) {
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
            if (!empty($stickyThreads)) {
                $_threads = array();
                foreach (array_keys($stickyThreads) as $_stickyThreadId) {
                    $_threads[$_stickyThreadId] = $stickyThreads[$_stickyThreadId];
                }
                foreach (array_keys($threads) as $_threadId) {
                    $_threads[$_threadId] = $threads[$_threadId];
                }
                $threads = $_threads;
                unset($_threads);
            }
        }

        $getForumById = false;
        if ($theForumId > 0
            && !$this->_isFieldExcluded('forum')
        ) {
            $getForumById = true;
        }
        if (!empty($pageNavParams['thread_prefix_id'])
            && !$this->_isFieldExcluded('thread_prefixes')
        ) {
            $getForumById = true;
        }
        if ($getForumById) {
            $theForum = $this->_getForumModel()->getForumById(
                $theForumId,
                $this->_getForumModel()->getFetchOptionsToPrepareApiData()
            );
        }

        $threadsData = $this->_prepareThreads($threads, $theForum);

        $data = array(
            'threads' => $this->_filterDataMany($threadsData),
        );

        if (!empty($theForum)
            && !$this->_isFieldExcluded('forum')
        ) {
            $data['forum'] = $this->_filterDataSingle($this->_getForumModel()
                ->prepareApiDataForForum($theForum), array('forum'));
        }

        if (!empty($creatorUser)
            && !$this->_isFieldExcluded('creator_user')
        ) {
            $data['creator_user'] = $this->_filterDataSingle($this->_getUserModel()
                ->prepareApiDataForUser($creatorUser), array('creator_user'));
        }

        if (!empty($pageNavParams['thread_prefix_id'])
            && !empty($theForum['prefixes'])
            && !$this->_isFieldExcluded('thread_prefixes')
        ) {
            $data['thread_prefixes'] = $this->_filterDataMany(
                $this->_getThreadPrefixModel()
                    ->prepareApiDataForPrefixes($theForum['prefixes'], array($pageNavParams['thread_prefix_id'])),
                array('thread_prefixes')
            );
        }

        if (!empty($threadTag)
            && !$this->_isFieldExcluded('thread_tags')
        ) {
            $data['thread_tag'] = $this->_getTagModel()->prepareApiDataForTag($threadTag);
        }

        if ($theForumId > 0) {
            $total = $this->_getThreadModel()->countThreads($conditions);
        }
        if ($total > 0) {
            $data['threads_total'] = $total;
            bdApi_Data_Helper_Core::addPageLinks(
                $this->getInput(),
                $data,
                $limit,
                $total,
                $page,
                'threads',
                array(),
                $pageNavParams
            );
        }

        return $this->responseData('bdApi_ViewApi_Thread_List', $data);
    }

    public function actionSingle()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable(
            $threadId,
            $this->_getThreadModel()->getFetchOptionsToPrepareApiData(),
            $this->_getForumModel()->getFetchOptionsToPrepareApiData()
        );

        $threads = array($threadId => $thread);
        $threadsData = $this->_prepareThreads($threads, $forum);

        $threadData = reset($threadsData);
        if (empty($threadData)) {
            return $this->responseNoPermission();
        }

        $data = array('thread' => $this->_filterDataSingle($threadData));

        return $this->responseData('bdApi_ViewApi_Thread_Single', $data);
    }

    public function actionMultiple()
    {
        $threadIdsInput = $this->_input->filterSingle('thread_ids', XenForo_Input::STRING);
        $threadIds = array_map('intval', explode(',', $threadIdsInput));
        if (empty($threadIds)) {
            return $this->responseNoPermission();
        }

        $threads = $this->_getThreadModel()->getThreadsByIds(
            $threadIds,
            $this->_getThreadModel()->getFetchOptionsToPrepareApiData()
        );

        $threadsOrdered = array();
        foreach ($threadIds as $threadId) {
            if (isset($threads[$threadId])) {
                $threadsOrdered[$threadId] = $threads[$threadId];
            }
        }

        $threadsData = $this->_prepareThreads($threadsOrdered);

        $data = array(
            'threads' => $this->_filterDataMany($threadsData),
        );

        return $this->responseData('bdApi_ViewApi_Thread_List', $data);
    }

    public function actionPostIndex()
    {
        $input = $this->_input->filter(array(
            'forum_id' => XenForo_Input::UINT,
            'thread_title' => XenForo_Input::STRING,
            'thread_prefix_id' => XenForo_Input::STRING,
            'thread_tags' => XenForo_Input::STRING,
        ));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['post_body'] = $editorHelper->getMessageText('post_body', $this->_input);
        $input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

        $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($input['forum_id']);

        $visitor = XenForo_Visitor::getInstance();

        if (!$this->_getForumModel()->canPostThreadInForum($forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        /* @var $writer XenForo_DataWriter_Discussion_Thread */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');

        // note: assumes that the message dw will pick up the username issues
        $writer->bulkSet(array(
            'user_id' => $visitor['user_id'],
            'username' => $visitor['username'],
            'title' => $input['thread_title'],
            'node_id' => $forum['node_id'],
        ));

        $prefixId = $input['thread_prefix_id'];
        if (!is_numeric($prefixId)
            && !empty($forum['default_prefix_id'])
        ) {
            $prefixId = $forum['default_prefix_id'];
        }
        $writer->set('prefix_id', $prefixId);

        // discussion state changes instead of first message state
        $writer->set('discussion_state', $this->_getPostModel()->getPostInsertMessageState(array(), $forum));

        /** @var bdApi_XenForo_DataWriter_DiscussionMessage_Post $postWriter */
        $postWriter = $writer->getFirstMessageDw();
        $postWriter->set('message', $input['post_body']);
        $postWriter->setExtraData(
            XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH,
            $this->_getAttachmentHelper()->getAttachmentTempHash($forum)
        );
        $postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();
        $clientId = $session->getOAuthClientId();
        if (!empty($clientId)) {
            $postWriter->bdApi_setOrigin($clientId);
        }

        $writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);

        $tagger = null;
        if (XenForo_Application::$versionId > 1050000
            && $this->_getThreadModel()->canEditTags(null, $forum)
        ) {
            // thread tagging is available since XenForo 1.5.0
            $tagModel = $this->_getTagModel();
            $tagger = $tagModel->getTagger('thread');
            $tagger->setPermissionsFromContext($forum)
                ->setTags($tagModel->splitTags($input['thread_tags']));
            $writer->mergeErrors($tagger->getErrors());
        }

        if ($writer->get('discussion_state') == 'visible') {
            switch ($this->_spamCheck(array(
                'content_type' => 'thread',
                'content' => $input['thread_title'] . "\n" . $input['post_body'],
            ))) {
                case XenForo_Model_SpamPrevention::RESULT_MODERATED:
                    $writer->set('discussion_state', 'moderated');
                    break;
                case XenForo_Model_SpamPrevention::RESULT_DENIED:
                    return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                    break;
            }
        }

        $writer->preSave();

        if (!$writer->get('prefix_id')
            && !empty($forum['require_prefix'])
        ) {
            /** @var bdApi_Extend_Model_ThreadPrefix $prefixModel */
            $prefixModel = $this->getModelFromCache('XenForo_Model_ThreadPrefix');
            if ($prefixModel->getUsablePrefixesInForums($forum['node_id'])) {
                $writer->error(new XenForo_Phrase('please_select_a_prefix'), 'prefix_id');
            }
        }

        if ($writer->hasErrors()) {
            return $this->responseErrors($writer->getErrors(), 400);
        }

        $this->assertNotFlooding('post');

        $writer->save();

        $thread = $writer->getMergedData();

        if (!empty($tagger)) {
            $tagger->setContent($thread['thread_id'], true)
                ->save();
        }

        $this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], array(
            // TODO
            'watch_thread_state' => 0,
            'watch_thread' => 0,
            'watch_thread_email' => 0,
        ));

        $this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);

        $this->_request->setParam('thread_id', $thread['thread_id']);
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionPutIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        $thread = $this->_getThreadModel()->getThreadById($threadId);
        $postId = 0;
        if (!empty($thread['first_post_id'])) {
            $postId = $thread['first_post_id'];
        }

        $this->_request->setParam('post_id', $postId);
        XenForo_Application::set('bdApi_responseReroute', array(__CLASS__, 'single'));
        return $this->responseReroute('bdApi_ControllerApi_Post', 'put-index');
    }

    public function actionDeleteIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        /** @var bdApi_ControllerHelper_Delete $deleteHelper */
        $deleteHelper = $this->getHelper('bdApi_ControllerHelper_Delete');
        $reason = $deleteHelper->filterReason();

        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        $deleteType = 'soft';
        $options = array('reason' => $reason);

        if (!$this->_getThreadModel()->canDeleteThread($thread, $forum, $deleteType, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $this->_getThreadModel()->deleteThread($thread['thread_id'], $deleteType, $options);

        XenForo_Model_Log::logModeratorAction(
            'thread',
            $thread,
            'delete_' . $deleteType,
            array('reason' => $options['reason'])
        );

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostAttachments()
    {
        $contentData = $this->_input->filter(array(
            'forum_id' => XenForo_Input::UINT,
        ));
        if (empty($contentData['forum_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_attachments_requires_forum_id'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        $response = $attachmentHelper->doUpload('file', $hash, 'post', $contentData);

        if ($response instanceof XenForo_ControllerResponse_Abstract) {
            return $response;
        }

        $contentData['post_id'] = 0;
        $data = array(
            'attachment' => $this->_filterDataSingle(
                $this->_getPostModel()->prepareApiDataForAttachment(
                    $response,
                    $contentData,
                    $contentData,
                    $contentData,
                    $hash
                )
            )
        );

        return $this->responseData('bdApi_ViewApi_Thread_Attachments', $data);
    }

    public function actionDeleteAttachments()
    {
        $contentData = $this->_input->filter(array('forum_id' => XenForo_Input::UINT));
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

        if (empty($contentData['forum_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_attachments_requires_forum_id'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        return $attachmentHelper->doDelete($hash, $attachmentId);
    }

    public function actionGetFollowed()
    {
        $this->_assertRegistrationRequired();

        $threadWatchModel = $this->_getThreadWatchModel();

        $total = $threadWatchModel->countThreadsWatchedByUser(XenForo_Visitor::getUserId());
        if ($this->_input->inRequest('total')) {
            $data = array('threads_total' => $total);
            return $this->responseData('bdApi_ViewApi_Thread_Followed_Total', $data);
        }

        $pageNavParams = array();
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page
        );

        $threadWatches = $threadWatchModel->getThreadsWatchedByUser(XenForo_Visitor::getUserId(), false, $fetchOptions);
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
                    $threadData = $threadWatchModel->prepareApiDataForThreadWatches($threadData, $threadWatch);
                    $threadsData[] = $threadData;
                }
            }
        }

        $data = array(
            'threads' => $this->_filterDataMany($threadsData),
            'threads_total' => $total,
        );

        bdApi_Data_Helper_Core::addPageLinks(
            $this->getInput(),
            $data,
            $limit,
            $total,
            $page,
            'threads/followed',
            array(),
            $pageNavParams
        );

        return $this->responseData('bdApi_ViewApi_Thread_Followed', $data);
    }

    public function actionGetFollowers()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        $users = array();

        if ($this->_getThreadModel()->canWatchThread($thread, $forum)) {
            $visitor = XenForo_Visitor::getInstance();

            /* @var $threadWatchModel bdApi_Extend_Model_ThreadWatch */
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
        $email = $this->_input->filterSingle('email', XenForo_Input::UINT);

        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

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

    public function actionPostPollVotes()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
        $ftpHelper = $this->getHelper('ForumThreadPost');
        list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

        $pollModel = $this->_getPollModel();
        $poll = $pollModel->getPollByContent('thread', $threadId);
        if (empty($poll)
            || !$this->_getThreadModel()->canVoteOnPoll($poll, $thread, $forum)
        ) {
            return $this->responseNoPermission();
        }

        return $pollModel->bdApi_actionPostVotes($poll, $this);
    }

    public function actionGetPollResults()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
        $ftpHelper = $this->getHelper('ForumThreadPost');
        list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

        $pollModel = $this->_getPollModel();
        $poll = $pollModel->getPollByContent('thread', $threadId);
        if (empty($poll)) {
            return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
        }

        return $pollModel->bdApi_actionGetResults(
            $poll,
            $this->_getThreadModel()->canVoteOnPoll($poll, $thread, $forum),
            $this
        );
    }

    public function actionGetNew()
    {
        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);

        $this->_assertRegistrationRequired();

        $visitor = XenForo_Visitor::getInstance();
        $threadModel = $this->_getThreadModel();

        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
        list($limit,) = $this->filterLimitAndPage();
        if ($limit > 0) {
            $maxResults = min($maxResults, $limit);
        }

        if (empty($forumId)) {
            $threadIds = $threadModel->getUnreadThreadIds($visitor->get('user_id'), array('limit' => $maxResults));
        } else {
            $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($forumId);
            $childNodeIds = array_keys($this->_getNodeModel()->getChildNodesForNodeIds(array($forum['node_id'])));

            $threadIds = $threadModel->bdApi_getUnreadThreadIdsInForum(
                $visitor->get('user_id'),
                array_merge(array($forum['node_id']), $childNodeIds),
                array('limit' => $maxResults)
            );
        }

        return $this->_getNewOrRecentResponse('threads_new', $threadIds);
    }

    public function actionGetRecent()
    {
        $threadModel = $this->_getThreadModel();

        $days = $this->_input->filterSingle('days', XenForo_Input::UINT);
        if ($days < 1) {
            $days = max(7, XenForo_Application::get('options')->readMarkingDataLifetime);
        }

        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
        list($limit,) = $this->filterLimitAndPage();
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

        return $this->_getNewOrRecentResponse('threads_recent', $threadIds);
    }

    protected function _prepareThreads(array $threads, array $forum = null)
    {
        // check for $forum being null because we only prepare `thread`.`forum` for request
        // of multiple threads from different forums (likely from actionMultiple)
        $prepareThreadForum = (!$this->_isFieldExcluded('forum') && $forum === null);

        $forums = array();
        if (!empty($forum)) {
            $forums[$forum['node_id']] = $forum;
        }

        $forumIds = array();
        foreach ($threads as $threadRef) {
            if (!isset($forums[$threadRef['node_id']])) {
                $forumIds[] = $threadRef['node_id'];
            }
        }
        if (!empty($forumIds)) {
            $forumIds = array_map('intval', $forumIds);
            $forumIds = array_unique($forumIds);
            $forums += $this->_getForumModel()->getForumsByIds(
                $forumIds,
                $this->_getForumModel()->getFetchOptionsToPrepareApiData()
            );
        }

        $visitor = XenForo_Visitor::getInstance();
        $nodePermissions = $this->_getNodeModel()->getNodePermissionsForPermissionCombination();
        foreach ($nodePermissions as $nodeId => $permissions) {
            $visitor->setNodePermissions($nodeId, $permissions);
        }

        $firstPostIds = array();
        $lastPostIds = array();
        $latestPostIds = array();
        $pollThreadIds = array();
        foreach ($threads as $threadId => $threadRef) {
            if (!$this->_isFieldExcluded('first_post')) {
                $firstPostIds[$threadId] = $threadRef['first_post_id'];
            }

            if ($this->_isFieldIncluded('last_post')
                && (!isset($firstPostIds[$threadId])
                    || $threadRef['last_post_id'] != $threadRef['first_post_id'])
            ) {
                $lastPostIds[$threadId] = $threadRef['last_post_id'];
            }

            if (!$this->_isFieldExcluded('poll')
                && $threadRef['discussion_type'] === 'poll'
            ) {
                $pollThreadIds[] = $threadId;
            }
        }

        if ($this->_isFieldIncluded('latest_posts')) {
            $latestPostIds = $this->_getThreadModel()->bdApi_getLatestPostIds(array_keys($threads));
        }

        $posts = array();
        if (!empty($firstPostIds)
            || !empty($lastPostIds)
            || !empty($latestPostIds)
        ) {
            $posts = $this->_getPostModel()->getPostsByIds(
                array_merge(array_values($firstPostIds), array_values($lastPostIds), $latestPostIds),
                $this->_getPostModel()->getFetchOptionsToPrepareApiData()
            );

            if ((!empty($firstPostIds) && !$this->_isFieldExcluded('first_post.attachments'))
                || (!empty($lastPostIds) && !$this->_isFieldExcluded('last_post.attachments'))
                || (!empty($latestPostIds) && !$this->_isFieldExcluded('latest_posts.*.attachments'))
            ) {
                $posts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($posts);
            }
        }

        if (!empty($pollThreadIds)) {
            $polls = $this->_getPollModel()->bdApi_getPollByContentIds('thread', $pollThreadIds);
            $this->_getThreadModel()->bdApi_setPolls($polls);
        }

        $threadsData = array();
        foreach ($threads as &$threadRef) {
            if (!isset($forums[$threadRef['node_id']])) {
                continue;
            }
            $forumRef =& $forums[$threadRef['node_id']];

            $firstPost = array();
            if (!empty($firstPostIds)
                && isset($posts[$threadRef['first_post_id']])
            ) {
                $firstPost = $posts[$threadRef['first_post_id']];
            }

            if (!$this->_getThreadModel()->canViewThreadAndContainer($threadRef, $forumRef)) {
                continue;
            }

            $threadData = $this->_getThreadModel()->prepareApiDataForThread($threadRef, $forumRef, $firstPost);

            if ($prepareThreadForum) {
                $threadData['forum'] = $this->_getForumModel()->prepareApiDataForForum($forumRef);
            }

            if (!empty($latestPostIds)) {
                $threadData['latest_posts'] = array();
                foreach ($posts as $post) {
                    if ($post['thread_id'] != $threadRef['thread_id']) {
                        continue;
                    }

                    if (!empty($firstPost)
                        && $post['post_id'] == $firstPost['post_id']
                    ) {
                        continue;
                    }

                    $threadData['latest_posts'][] = $this->_getPostModel()
                        ->prepareApiDataForPost($post, $threadRef, $forumRef);
                }
            } elseif (!empty($lastPostIds)
                && isset($posts[$threadRef['last_post_id']])
            ) {
                $threadData['last_post'] = $this->_getPostModel()->prepareApiDataForPost(
                    $posts[$threadRef['last_post_id']],
                    $threadRef,
                    $forumRef
                );
            }

            $threadsData[] = $threadData;
        }

        return $threadsData;
    }

    protected function _getNewOrRecentResponse($searchType, array $threadIds)
    {
        $visitor = XenForo_Visitor::getInstance();
        $threadModel = $this->_getThreadModel();

        $results = array();
        $threads = $threadModel->getThreadsByIds($threadIds, array(
            'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
            'permissionCombinationId' => $visitor['permission_combination_id'],
        ));
        foreach ($threadIds AS $threadId) {
            if (!isset($threads[$threadId])) {
                continue;
            }
            $threadRef = &$threads[$threadId];

            $threadRef['permissions'] = XenForo_Permission::unserializePermissions($threadRef['node_permission_cache']);

            if ($threadModel->canViewThreadAndContainer($threadRef, $threadRef, $null, $threadRef['permissions'])
                && !$visitor->isIgnoring($threadRef['user_id'])
            ) {
                $results[] = array('thread_id' => $threadId);
            }
        }

        $data = array('threads' => $results);

        list($dataLimit,) = $this->filterLimitAndPage($tempArray, 'data_limit');
        if ($dataLimit > 0) {
            $searchResults = array();
            foreach ($results as $result) {
                $searchResults[] = array(
                    XenForo_Model_Search::CONTENT_TYPE => 'thread',
                    XenForo_Model_Search::CONTENT_ID => $result['thread_id']
                );
            }

            /** @var bdApi_Extend_Model_Search $searchModel */
            $searchModel = $this->getModelFromCache('XenForo_Model_Search');
            $search = $searchModel->insertSearch($searchResults, $searchType, '', array(), 'date', false);

            $dataResults = array_slice($searchResults, 0, $dataLimit);
            $dataResults = $searchModel->prepareApiDataForSearchResults($dataResults);
            $data['data'] = $searchModel->prepareApiContentDataForSearch($dataResults);

            bdApi_Data_Helper_Core::addPageLinks(
                $this->getInput(),
                $data,
                $dataLimit,
                $search['result_count'],
                1,
                'search/results',
                $search,
                array('limit' => $dataLimit)
            );
        }

        return $this->responseData('bdApi_ViewApi_Thread_NewOrRecent', $data);
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Node');
    }

    /**
     * @return bdApi_Extend_Model_Forum
     */
    protected function _getForumModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Forum');
    }

    /**
     * @return bdApi_Extend_Model_Thread
     */
    protected function _getThreadModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Thread');
    }

    /**
     * @return bdApi_Extend_Model_Post
     */
    protected function _getPostModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Post');
    }

    /**
     * @return bdApi_Extend_Model_Poll
     */
    protected function _getPollModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Poll');
    }

    /**
     * @return bdApi_Extend_Model_Tag
     */
    protected function _getTagModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Tag');
    }

    /**
     * @return bdApi_Extend_Model_ThreadPrefix
     */
    protected function _getThreadPrefixModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
    }

    /**
     * @return bdApi_Extend_Model_ThreadWatch
     */
    protected function _getThreadWatchModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_ThreadWatch');
    }

    /**
     * @return bdApi_Extend_Model_User
     */
    protected function _getUserModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_User');
    }

    /**
     * @return XenForo_Model_UserProfile
     */
    protected function _getUserProfileModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_UserProfile');
    }

    /**
     * @return XenForo_ControllerHelper_ForumThreadPost
     */
    protected function _getForumThreadPostHelper()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getHelper('ForumThreadPost');
    }

    /**
     * @return bdApi_ControllerHelper_Attachment
     */
    protected function _getAttachmentHelper()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getHelper('bdApi_ControllerHelper_Attachment');
    }

    public function _isFieldExcluded($field, array $prefixes = array(), $hasChild = true)
    {
        if ($field === 'thread_id') {
            return false;
        }

        return parent::_isFieldExcluded($field, $prefixes);
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        switch ($action) {
            case 'GetIndex':
                $forumId = $this->_request->getParam('forum_id');
                if (!empty($forumId)
                    && is_numeric($forumId)
                ) {
                    $params['node_id'] = $forumId;
                }
                $controllerName = 'XenForo_ControllerPublic_Forum';
                break;
            case 'Single':
                $controllerName = 'XenForo_ControllerPublic_Thread';
                break;
            case 'GetNew':
            case 'GetRecent':
                $controllerName = 'XenForo_ControllerPublic_FindNew';
                break;
            default:
                parent::_prepareSessionActivityForApi($controllerName, $action, $params);
        }
    }
}
