<?php

class bdApi_ControllerApi_ProfilePost extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $profilePostIds = $this->_input->filterSingle('profile_post_ids', XenForo_Input::STRING);
        if (!empty($profilePostIds)) {
            return $this->responseReroute(__CLASS__, 'multiple');
        }

        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionSingle()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable(
            $profilePostId,
            $this->_getProfilePostModel()->getFetchOptionsToPrepareApiData()
        );

        $data = array('profile_post' => $this->_filterDataSingle(
            $this->_getProfilePostModel()->prepareApiDataForProfilePost($profilePost, $user)
        ));

        return $this->responseData('bdApi_ViewApi_ProfilePost_Single', $data);
    }

    public function actionMultiple()
    {
        $profilePostIdsInput = $this->_input->filterSingle('profile_post_ids', XenForo_Input::STRING);
        $profilePostIds = array_map('intval', explode(',', $profilePostIdsInput));
        if (empty($profilePostIds)) {
            return $this->responseNoPermission();
        }

        $profilePosts = $this->_getProfilePostModel()->getProfilePostsByIds(
            $profilePostIds,
            $this->_getProfilePostModel()->getFetchOptionsToPrepareApiData()
        );

        $profilePostsOrdered = array();
        $profileUserIds = array();
        foreach ($profilePostIds as $profilePostId) {
            if (isset($profilePosts[$profilePostId])) {
                $profilePostsOrdered[$profilePostId] = $profilePosts[$profilePostId];
                $profileUserIds[] = $profilePosts[$profilePostId]['profile_user_id'];
            }
        }

        $profileUserIds = array_unique(array_map('intval', $profileUserIds));
        if (!empty($profileUserIds)) {
            /** @var XenForo_Model_User $userModel */
            $userModel = $this->getModelFromCache('XenForo_Model_User');
            $profileUsers = $userModel->getUsersByids($profileUserIds, array(
                'join' => XenForo_Model_User::FETCH_USER_FULL,
            ));
        }

        $profilePostsData = array();
        foreach ($profilePostsOrdered as $profilePost) {
            if (!isset($profileUsers[$profilePost['profile_user_id']])) {
                continue;
            }
            $profileUserRef = $profileUsers[$profilePost['profile_user_id']];

            $profilePostsData[] = $this->_getProfilePostModel()->prepareApiDataForProfilePost($profilePost, $profileUserRef);
        }

        $data = array(
            'profile_posts' => $this->_filterDataMany($profilePostsData),
        );

        return $this->responseData('bdApi_ViewApi_ProfilePost_Multiple', $data);
    }

    public function actionPostIndex()
    {
        /** @var XenForo_Model_User $userModel */
        $userModel = $this->getModelFromCache('XenForo_Model_User');
        /** @var XenForo_Model_UserProfile $userProfileModel */
        $userProfileModel = $this->getModelFromCache('XenForo_Model_UserProfile');
        /** @var bdApi_Extend_Model_ProfilePost $profilePostModel */
        $profilePostModel = $this->getModelFromCache('XenForo_Model_ProfilePost');

        $userId = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        $postBody = $this->_input->filterSingle('post_body', XenForo_Input::STRING);

        $user = $userModel->getFullUserById($userId);
        if (empty($user)) {
            return $this->responseNoPermission();
        }

        if (!$userProfileModel->canViewProfilePosts($user)) {
            return $this->responseNoPermission();
        }

        $visitor = XenForo_Visitor::getInstance();
        if ($user['user_id'] == $visitor->get('user_id')) {
            if (!$visitor->canUpdateStatus()) {
                return $this->responseNoPermission();
            }

            if (empty($postBody)) {
                // special support for status
                $postBody = $this->_input->filterSingle('status', XenForo_Input::STRING);
            }

            try {
                $profilePostId = $userProfileModel->updateStatus($postBody);
            } catch (XenForo_Exception $e) {
                return $this->responseError($e->getMessage(), 400);
            }
        } else {
            if (!$userProfileModel->canPostOnProfile($user)) {
                return $this->responseNoPermission();
            }

            $writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');

            $writer->set('user_id', $visitor['user_id']);
            $writer->set('username', $visitor['username']);
            $writer->set('message', $postBody);
            $writer->set('profile_user_id', $user['user_id']);
            $writer->set('message_state', $profilePostModel->getProfilePostInsertMessageState($user));
            $writer->setExtraData(XenForo_DataWriter_DiscussionMessage_ProfilePost::DATA_PROFILE_USER, $user);
            $writer->setOption(XenForo_DataWriter_DiscussionMessage_ProfilePost::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));

            if ($writer->get('message_state') == 'visible') {
                switch ($this->_spamCheck(array(
                    'content_type' => 'profile_post',
                    'content' => $postBody,
                ))) {
                    case XenForo_Model_SpamPrevention::RESULT_MODERATED:
                        $writer->set('message_state', 'moderated');
                        break;
                    case XenForo_Model_SpamPrevention::RESULT_DENIED;
                        return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                        break;
                }
            }

            $writer->preSave();

            if ($writer->hasErrors()) {
                return $this->responseErrors($writer->getErrors(), 400);
            }

            $this->assertNotFlooding('post');

            $writer->save();
            $profilePostId = $writer->get('profile_post_id');
        }

        $this->_request->setParam('profile_post_id', $profilePostId);
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionPutIndex()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        $postBody = $this->_input->filterSingle('post_body', XenForo_Input::STRING);

        list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable($profilePostId);

        if (!$this->_getProfilePostModel()->canEditProfilePost($profilePost, $user, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
        $dw->setExistingData($profilePost, true);
        $dw->set('message', $postBody);

        if ($dw->get('message_state') == 'visible') {
            switch ($this->_spamCheck(array(
                'content_type' => 'profile_post',
                'content_id' => $profilePostId,
                'content' => $postBody,
            ))) {
                case XenForo_Model_SpamPrevention::RESULT_MODERATED:
                    $dw->set('message_state', 'moderated');
                    break;
                case XenForo_Model_SpamPrevention::RESULT_DENIED;
                    return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                    break;
            }
        }

        $dw->preSave();

        if ($dw->hasErrors()) {
            return $this->responseErrors($dw->getErrors(), 400);
        }

        $dw->save();

        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionDeleteIndex()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable($profilePostId);

        $deleteType = 'soft';
        $options = array('reason' => '[bd] API');

        if (!$this->_getProfilePostModel()->canDeleteProfilePost($profilePost, $user, $deleteType, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
        $dw->setExistingData($profilePost, true);
        $dw->set('message_state', 'deleted');
        $dw->save();

        XenForo_Model_Log::logModeratorAction(
            'profile_post',
            $profilePost,
            'delete_soft',
            $options,
            $user
        );

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetLikes()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        list($profilePost,) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable($profilePostId);

        $likes = $this->_getLikeModel()->getContentLikes('profile_post', $profilePost['profile_post_id']);
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

        return $this->responseData('bdApi_ViewApi_ProfilePost_Likes', $data);
    }

    public function actionPostLikes()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable($profilePostId);

        if (!$this->_getProfilePostModel()->canLikeProfilePost($profilePost, $user, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $likeModel = $this->_getLikeModel();

        $existingLike = $likeModel->getContentLikeByLikeUser('profile_post', $profilePost['profile_post_id'], XenForo_Visitor::getUserId());
        if (empty($existingLike)) {
            $latestUsers = $likeModel->likeContent('profile_post', $profilePost['profile_post_id'], $profilePost['user_id']);

            if ($latestUsers === false) {
                return $this->responseNoPermission();
            }
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionDeleteLikes()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable($profilePostId);

        if (!$this->_getProfilePostModel()->canLikeProfilePost($profilePost, $user, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $likeModel = $this->_getLikeModel();

        $existingLike = $likeModel->getContentLikeByLikeUser('profile_post', $profilePost['profile_post_id'], XenForo_Visitor::getUserId());
        if (!empty($existingLike)) {
            $latestUsers = $likeModel->unlikeContent($existingLike);

            if ($latestUsers === false) {
                return $this->responseNoPermission();
            }
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionGetComments()
    {
        $pageOfCommentId = $this->_input->filterSingle('page_of_comment_id', XenForo_Input::UINT);
        $pageOfComment = null;
        $commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
        $beforeDate = $this->_input->filterSingle('before', XenForo_Input::UINT);

        if (!empty($pageOfCommentId)) {
            list($pageOfComment, $profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostCommentValidAndViewable(
                $pageOfCommentId,
                $this->_getProfilePostModel()->getCommentFetchOptionsToPrepareApiData(),
                $this->_getProfilePostModel()->getFetchOptionsToPrepareApiData(),
                $this->_getUserModel()->getFetchOptionsToPrepareApiData()
            );
            $profilePostId = $profilePost['profile_post_id'];
        } else {
            $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
            if (empty($profilePostId)) {
                return $this->responseNoPermission();
            }

            list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable(
                $profilePostId,
                $this->_getProfilePostModel()->getFetchOptionsToPrepareApiData(),
                $this->_getUserModel()->getFetchOptionsToPrepareApiData()
            );

            // special case for single comment
            if (!empty($commentId)) {
                list($comment, ,) = $this->_getUserProfileHelper()->assertProfilePostCommentValidAndViewable(
                    $commentId,
                    $this->_getProfilePostModel()->getCommentFetchOptionsToPrepareApiData()
                );
                if ($comment['profile_post_id'] != $profilePost['profile_post_id']) {
                    return $this->responseNoPermission();
                }

                $data = array(
                    'comment' => $this->_filterDataSingle($this->_getProfilePostModel()->prepareApiDataForComment($comment, $profilePost, $user)),
                );

                return $this->responseData('bdApi_ViewApi_ProfilePost_Comments_Single', $data);
            }
        }

        $pageNavParams = array();

        $limit = XenForo_Application::get('options')->messagesPerPage;
        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

        if (!empty($pageOfComment)) {
            $beforeDate = $pageOfComment['comment_date'] + 1;
        }

        $fetchOptions = array(
            'limit' => $limit,
        );

        $comments = $this->_getProfilePostModel()->getProfilePostCommentsByProfilePost(
            $profilePostId,
            $beforeDate,
            $this->_getProfilePostModel()->getCommentFetchOptionsToPrepareApiData($fetchOptions)
        );
        $oldestComment = reset($comments);
        $latestComment = end($comments);

        $total = $profilePost['comment_count'];

        $data = array(
            'comments' => $this->_filterDataMany($this->_getProfilePostModel()->prepareApiDataForComments($comments, $profilePost, $user)),
            'comments_total' => $total,

            '_profilePost' => $profilePost,
            '_user' => $user,
        );

        if (!$this->_isFieldExcluded('profile_post')) {
            $data['profile_post'] = $this->_filterDataSingle($this->_getProfilePostModel()->prepareApiDataForProfilePost($profilePost, $user), array('profile_post'));
        }

        if (!$this->_isFieldExcluded('timeline_user')) {
            $data['timeline_user'] = $this->_filterDataSingle($this->_getUserModel()->prepareApiDataForUser($user), array('timeline_user'));
        }

        $inputData = $this->_input->filter(array(
            'fields_include' => XenForo_Input::STRING,
            'fields_exclude' => XenForo_Input::STRING,
        ));
        if (!empty($inputData['fields_include'])) {
            $pageNavParams['fields_include'] = $inputData['fields_include'];
        } elseif (!empty($inputData['fields_exclude'])) {
            $pageNavParams['fields_exclude'] = $inputData['fields_exclude'];
        }
        if ($oldestComment['comment_date'] != $profilePost['first_comment_date']) {
            $data['links']['prev'] = bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts/comments', $profilePost, array_merge($pageNavParams, array(
                'before' => $oldestComment['comment_date'],
            )));
        }
        if ($latestComment['comment_date'] != $profilePost['last_comment_date']) {
            $data['links']['latest'] = bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts/comments', $profilePost, $pageNavParams);
        }

        return $this->responseData('bdApi_ViewApi_ProfilePost_Comments', $data);
    }

    public function actionPostComments()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        $commentBody = $this->_input->filterSingle('comment_body', XenForo_Input::STRING);

        list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable($profilePostId);

        if (!$this->_getProfilePostModel()->canCommentOnProfilePost($profilePost, $user, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $visitor = XenForo_Visitor::getInstance();

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment');
        $dw->setExtraData(XenForo_DataWriter_ProfilePostComment::DATA_PROFILE_USER, $user);
        $dw->setExtraData(XenForo_DataWriter_ProfilePostComment::DATA_PROFILE_POST, $profilePost);
        $dw->set('message_state', $this->_getProfilePostModel()->getProfilePostCommentInsertMessageState($profilePost));
        $dw->bulkSet(array(
            'profile_post_id' => $profilePost['profile_post_id'],
            'user_id' => $visitor['user_id'],
            'username' => $visitor['username'],
            'message' => $commentBody
        ));
        $dw->setOption(XenForo_DataWriter_ProfilePostComment::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));

        if ($dw->get('message_state') == 'visible') {
            switch ($this->_spamCheck(array(
                'content_type' => 'profile_post_comment',
                'content' => $commentBody,
            ))) {
                case XenForo_Model_SpamPrevention::RESULT_MODERATED:
                    $dw->set('message_state', 'moderated');
                    break;
                case XenForo_Model_SpamPrevention::RESULT_DENIED;
                    return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                    break;
            }
        }

        $dw->preSave();

        if ($dw->hasErrors()) {
            return $this->responseErrors($dw->getErrors(), 400);
        }

        $this->assertNotFlooding('post');

        $dw->save();
        $comment = $dw->getMergedData();

        $this->_request->setParam('comment_id', $comment['profile_post_comment_id']);
        return $this->responseReroute(__CLASS__, 'get-comments');
    }

    public function actionDeleteComments()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);

        $commentId = $this->_input->filterSingle('comment_id', XenForo_Input::UINT);
        list($comment, $profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostCommentValidAndViewable(
            $commentId,
            $this->_getProfilePostModel()->getCommentFetchOptionsToPrepareApiData()
        );
        if ($profilePost['profile_post_id'] != $profilePostId) {
            return $this->responseNoPermission();
        }

        $errorPhraseKey = '';
        if (XenForo_Application::$versionId > 1050051) {
            $canDelete = call_user_func_array(array($this->_getProfilePostModel(), 'canDeleteProfilePostComment'), array(
                $comment, $profilePost, $user, 'soft', &$errorPhraseKey
            ));
        } else {
            $canDelete = call_user_func_array(array($this->_getProfilePostModel(), 'canDeleteProfilePostComment'), array(
                $comment, $profilePost, $user, &$errorPhraseKey
            ));
        }
        if (!$canDelete) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_ProfilePostComment');
        $dw->setExistingData($commentId);
        $dw->delete();

        XenForo_Model_Log::logModeratorAction(
            'profile_post',
            $profilePost,
            'comment_delete',
            array(
                'username' => $comment['username'],
                'reason' => '[bd] API',
            ),
            $user
        );

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostReport()
    {
        $profilePostId = $this->_input->filterSingle('profile_post_id', XenForo_Input::UINT);
        $message = $this->_input->filterSingle('message', XenForo_Input::STRING);

        list($profilePost, $user) = $this->_getUserProfileHelper()->assertProfilePostValidAndViewable($profilePostId);

        if (!$this->_getProfilePostModel()->canReportProfilePost($profilePost, $user, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        if (!$message) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_x_report_requires_message', array('route' => 'profile-posts')), 400);
        }

        $this->assertNotFlooding('report');

        /* @var $reportModel XenForo_Model_Report */
        $reportModel = $this->getModelFromCache('XenForo_Model_Report');
        $reportModel->reportContent('profile_post', $profilePost, $message);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    /**
     * @return XenForo_ControllerHelper_UserProfile
     */
    protected function _getUserProfileHelper()
    {
        return $this->getHelper('UserProfile');
    }

    /**
     * @return bdApi_XenForo_Model_User
     */
    protected function _getUserModel()
    {
        return $this->getModelFromCache('XenForo_Model_User');
    }

    /**
     * @return bdApi_Extend_Model_ProfilePost
     */
    protected function _getProfilePostModel()
    {
        return $this->getModelFromCache('XenForo_Model_ProfilePost');
    }

    /**
     * @return XenForo_Model_Like
     */
    protected function _getLikeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Like');
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'XenForo_ControllerPublic_ProfilePost';
    }
}