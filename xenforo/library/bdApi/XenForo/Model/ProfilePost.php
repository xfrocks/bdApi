<?php

class bdApi_XenForo_Model_ProfilePost extends XFCP_bdApi_XenForo_Model_ProfilePost
{
    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        if (empty($fetchOptions['join'])) {
            $fetchOptions['join'] = 0;
        }

        $fetchOptions['join'] |= XenForo_Model_ProfilePost::FETCH_USER_POSTER;

        $fetchOptions['likeUserId'] = XenForo_Visitor::getUserId();

        return $fetchOptions;
    }

    public function prepareApiDataForProfilePosts(array $profilePosts, array $user)
    {
        $data = array();

        foreach ($profilePosts as $key => $profilePost) {
            $data[] = $this->prepareApiDataForProfilePost($profilePost, $user);
        }

        return $data;
    }

    public function prepareApiDataForProfilePost(array $profilePost, array $user)
    {
        $profilePost = $this->prepareProfilePost($profilePost, $user);

        $publicKeys = array(
            // xf_profile_post
            'profile_post_id' => 'profile_post_id',
            'profile_user_id' => 'timeline_user_id',
            'user_id' => 'poster_user_id',
            'username' => 'poster_username',
            'post_date' => 'post_create_date',
            'message' => 'post_body',
            'likes' => 'post_like_count',
            'comment_count' => 'post_comment_count',
        );

        $data = bdApi_Data_Helper_Core::filter($profilePost, $publicKeys);

        $data['user_is_ignored'] = XenForo_Visitor::getInstance()->isIgnoring($profilePost['user_id']);

        if (isset($profilePost['message_state'])) {
            switch ($profilePost['message_state']) {
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

        if (isset($profilePost['like_date'])) {
            $data['post_is_liked'] = !empty($profilePost['like_date']);
        }

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('profile-posts', $profilePost),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts', $profilePost),
            'timeline' => bdApi_Data_Helper_Core::safeBuildApiLink('users/timeline', $user),
            'timeline_user' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $user),
            'poster' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $profilePost),
            'likes' => bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts/likes', $profilePost),
            'comments' => bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts/comments', $profilePost),
            'report' => bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts/report', $profilePost),
            'poster_avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array(
                $profilePost,
                'm',
                false,
                true
            )),
        );

        $data['permissions'] = array(
            'view' => $this->canViewProfilePost($profilePost, $user),
            'edit' => $this->canEditProfilePost($profilePost, $user),
            'delete' => $this->canDeleteProfilePost($profilePost, $user),
            'like' => $this->canLikeProfilePost($profilePost, $user),
            'comment' => $this->canCommentOnProfilePost($profilePost, $user),
            'report' => $this->canReportProfilePost($profilePost, $user),
        );

        return $data;
    }

    public function getCommentFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        if (empty($fetchOptions['join'])) {
            $fetchOptions['join'] = 0;
        }

        $fetchOptions['join'] |= XenForo_Model_ProfilePost::FETCH_COMMENT_USER;

        return $fetchOptions;
    }

    public function prepareApiDataForComments(array $comments, array $profilePost, array $user)
    {
        $data = array();

        foreach ($comments as $key => $comment) {
            $data[] = $this->prepareApiDataForComment($comment, $profilePost, $user);
        }

        return $data;
    }

    public function prepareApiDataForComment(array $comment, array $profilePost, array $user)
    {
        $comment = $this->prepareProfilePostComment($comment, $profilePost, $user);

        $publicKeys = array(
            // xf_profile_post_comment
            'profile_post_comment_id' => 'comment_id',
            'profile_post_id' => 'profile_post_id',
            'user_id' => 'comment_user_id',
            'username' => 'comment_username',
            'comment_date' => 'comment_create_date',
            'message' => 'comment_body',
        );

        $data = bdApi_Data_Helper_Core::filter($comment, $publicKeys);

        $data['user_is_ignored'] = XenForo_Visitor::getInstance()->isIgnoring($comment['user_id']);
        $data['timeline_user_id'] = $profilePost['profile_user_id'];

        $data['links'] = array(
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts/comments', $profilePost, array('comment_id' => $comment['profile_post_comment_id'])),
            'profile_post' => bdApi_Data_Helper_Core::safeBuildApiLink('profile-posts', $profilePost),
            'timeline' => bdApi_Data_Helper_Core::safeBuildApiLink('users/timeline', $user),
            'timeline_user' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $user),
            'poster' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $comment),
            'poster_avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array(
                $comment,
                'm',
                false,
                true
            )),
        );

        $data['permissions'] = array(
            'view' => $this->canViewProfilePost($profilePost, $user),
            'delete' => $this->canDeleteProfilePostComment($comment, $profilePost, $user),
        );

        return $data;
    }
}