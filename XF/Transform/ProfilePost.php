<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class ProfilePost extends AbstractHandler
{
    const KEY_POSTER_USER_ID = 'poster_user_id';
    const KEY_POSTER_USERNAME = 'poster_username';
    const KEY_POST_CREATE_DATE = 'post_create_date';
    const KEY_POST_LIKE_COUNT = 'post_like_count';
    const KEY_POST_COMMENT_COUNT = 'post_comment_count';

    const DYNAMIC_KEY_POST_BODY = 'post_body';
    const DYNAMIC_KEY_TIMELINE_USER_ID = 'timeline_user_id';
    const DYNAMIC_KEY_TIMELINE_USER_NAME = 'timeline_username';
    const DYNAMIC_KEY_POST_IS_PUBLISHED = 'post_is_published';
    const DYNAMIC_KEY_POST_IS_DELETED = 'post_is_deleted';
    const DYNAMIC_KEY_POST_IS_LIKED = 'post_is_liked';
    const DYNAMIC_KEY_USER_IS_IGNORED = 'user_is_ignored';

    const LINK_TIMELINE = 'timeline';
    const LINK_TIMELINE_USER = 'timeline_user';
    const LINK_POSTER = 'poster';
    const LINK_COMMENTS = 'comments';
    const LINK_POSTER_AVATAR = 'poster_avatar';

    const PERM_COMMENT = 'comment';

    public function getMappings($context)
    {
        return [
            'user_id' => self::KEY_POSTER_USER_ID,
            'username' => self::KEY_POSTER_USERNAME,
            'post_date' => self::KEY_POST_CREATE_DATE,
            'likes' => self::KEY_POST_LIKE_COUNT,
            'comment_count' => self::KEY_POST_COMMENT_COUNT,

            self::DYNAMIC_KEY_POST_BODY,
            self::DYNAMIC_KEY_TIMELINE_USER_ID,
            self::DYNAMIC_KEY_TIMELINE_USER_NAME,
            self::DYNAMIC_KEY_POST_IS_PUBLISHED,
            self::DYNAMIC_KEY_POST_IS_DELETED,
            self::DYNAMIC_KEY_POST_IS_LIKED,
            self::DYNAMIC_KEY_USER_IS_IGNORED
        ];
    }

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XF\Entity\ProfilePost $profilePost */
        $profilePost = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_POST_BODY:
                break;
            case self::DYNAMIC_KEY_TIMELINE_USER_ID:
                return $profilePost->ProfileUser->user_id;
            case self::DYNAMIC_KEY_TIMELINE_USER_NAME:
                return $profilePost->ProfileUser->username;
            case self::DYNAMIC_KEY_POST_IS_PUBLISHED:
                return $profilePost->isVisible();
            case self::DYNAMIC_KEY_POST_IS_DELETED:
                return $profilePost->message_state === 'deleted';
            case self::DYNAMIC_KEY_POST_IS_LIKED:
                return $profilePost->isLiked();
            case self::DYNAMIC_KEY_USER_IS_IGNORED:
                return $profilePost->isIgnored();
        }

        return null;
    }

    public function collectLinks($context)
    {
        /** @var \XF\Entity\ProfilePost $profilePost */
        $profilePost = $context->getSource();

        $links = [
            self::LINK_PERMALINK => $this->buildPublicLink('profile-posts', $profilePost),
            self::LINK_DETAIL => $this->buildApiLink('profile-posts', $profilePost),
            self::LINK_TIMELINE => $this->buildApiLink('users/timeline', $profilePost->ProfileUser),
            self::LINK_TIMELINE_USER => $this->buildApiLink('users', $profilePost->ProfileUser),
            self::LINK_POSTER => $this->buildApiLink('users', $profilePost->User),
            self::LINK_LIKES => $this->buildApiLink('profile-posts/likes', $profilePost),
            self::LINK_COMMENTS => $this->buildApiLink('profile-posts/comments', $profilePost),
            self::LINK_REPORT => $this->buildApiLink('profile-posts/report', $profilePost),
            self::LINK_POSTER_AVATAR => $profilePost->User->getAvatarUrl('m')
        ];

        return $links;
    }

    public function collectPermissions($context)
    {
        /** @var \XF\Entity\ProfilePost $profilePost */
        $profilePost = $context->getSource();

        $perms = [
            self::PERM_VIEW => $profilePost->canView(),
            self::PERM_EDIT => $profilePost->canEdit(),
            self::PERM_DELETE => $profilePost->canDelete(),
            self::PERM_LIKE => $profilePost->canLike(),
            self::PERM_REPORT => $profilePost->canReport(),
            self::PERM_COMMENT => $profilePost->canComment()
        ];

        return $perms;
    }
}