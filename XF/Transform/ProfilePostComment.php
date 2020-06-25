<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

class ProfilePostComment extends AbstractHandler
{
    const KEY_ID = 'comment_id';
    const KEY_PROFILE_POST_ID = 'profile_post_id';
    const KEY_COMMENT_USER_ID = 'comment_user_id';
    const KEY_COMMENT_USERNAME = 'comment_username';
    const KEY_COMMENT_CREATE_DATE = 'comment_create_date';

    const DYNAMIC_KEY_TIMELINE_USER_ID = 'timeline_user_id';
    const DYNAMIC_KEY_USER_IS_IGNORED = 'user_is_ignored';
    const DYNAMIC_KEY_COMMENT_BODY = 'comment_body';

    const LINK_PROFILE_POST = 'profile_post';
    const LINK_TIMELINE = 'timeline';
    const LINK_TIMELINE_USER = 'timeline_user';
    const LINK_POSTER = 'poster';
    const LINK_POSTER_AVATAR = 'poster_avatar';

    public function getMappings(TransformContext $context)
    {
        return [
            'profile_post_comment_id' => self::KEY_ID,
            'profile_post_id' => self::KEY_PROFILE_POST_ID,
            'user_id' => self::KEY_COMMENT_USER_ID,
            'username' => self::KEY_COMMENT_USERNAME,
            'comment_date' => self::KEY_COMMENT_CREATE_DATE,

            self::DYNAMIC_KEY_TIMELINE_USER_ID,
            self::DYNAMIC_KEY_USER_IS_IGNORED,
            self::DYNAMIC_KEY_COMMENT_BODY
        ];
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var \XF\Entity\ProfilePostComment $comment */
        $comment = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_TIMELINE_USER_ID:
                $profilePost = $comment->ProfilePost;
                if ($profilePost === null) {
                    break;
                }

                $profileUser = $profilePost->ProfileUser;
                if ($profileUser === null) {
                    break;
                }

                return $profileUser->user_id;
            case self::DYNAMIC_KEY_COMMENT_BODY:
                return $this->app->templater()->func('structured_text', [$comment->message]);
            case self::DYNAMIC_KEY_USER_IS_IGNORED:
                return $comment->isIgnored();
        }

        return null;
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var \XF\Entity\ProfilePostComment $comment */
        $comment = $context->getSource();
        $profilePost = $comment->ProfilePost;
        $user = $comment->User;

        $links = [
            self::LINK_DETAIL => $this->buildApiLink(
                'profile-posts/comments',
                $comment->ProfilePost,
                ['comment_id' => $comment->profile_post_comment_id]
            ),
            self::LINK_PROFILE_POST => $this->buildApiLink('profile-posts', $profilePost),
            self::LINK_TIMELINE => $profilePost !== null
                ? $this->buildApiLink('users/timeline', $profilePost->ProfileUser)
                : null,
            self::LINK_TIMELINE_USER => $profilePost !== null
                ? $this->buildApiLink('users', $profilePost->ProfileUser)
                : null,
        ];

        if ($user !== null) {
            $links[self::LINK_POSTER] = $this->buildApiLink('users', $user);
            $links[self::LINK_POSTER_AVATAR] = $user->getAvatarUrl('m');
        }

        return $links;
    }

    public function collectPermissions(TransformContext $context)
    {
        /** @var \XF\Entity\ProfilePostComment $comment */
        $comment = $context->getSource();

        $perms = [
            self::PERM_VIEW => $comment->canView(),
            self::PERM_DELETE => $comment->canDelete()
        ];

        return $perms;
    }
}
