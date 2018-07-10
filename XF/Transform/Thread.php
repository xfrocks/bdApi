<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class Thread extends AbstractHandler
{
    const KEY_ID = 'thread_id';
    const KEY_TITLE = 'thread_title';
    const KEY_CREATOR_USER_ID = 'creator_user_id';
    const KEY_CREATOR_USERNAME = 'creator_username';
    const KEY_POST_COUNT = 'thread_post_count';
    const KEY_VIEW_COUNT = 'thread_view_count';
    const KEY_CREATE_DATE = 'thread_create_date';
    const KEY_FORUM_ID = 'forum_id';
    const KEY_UPDATE_DATE  = 'thread_update_date';

    const DYNAMIC_KEY_USER_IS_IGNORED = 'user_is_ignored';
    const DYNAMIC_KEY_IS_PUBLISHED = 'thread_is_published';
    const DYNAMIC_KEY_IS_DELETED = 'thread_is_deleted';
    const DYNAMIC_KEY_IS_STICKY = 'thread_is_sticky';
    const DYNAMIC_KEY_IS_FOLLOWED = 'thread_is_followed';
    const DYNAMIC_KEY_FIRST_POST = 'first_post';
//    const DYNAMIC_KEY_LAST_POST = 'last_post';
    const DYNAMIC_KEY_PREFIXES = 'thread_prefixes';
    const DYNAMIC_KEY_TAGS = 'thread_tags';
    const DYNAMIC_KEY_POLL = 'poll';

    const LINK_FORUM = 'forum';
    const LINK_POSTS = 'posts';
    const LINK_FIRST_POSTER = 'first_poster';
    const LINK_FIRST_POSTER_AVATAR = 'first_poster_avatar';
    const LINK_FIRST_POST = 'first_post';
    const LINK_LAST_POSTER = 'last_poster';
    const LINK_LAST_POST = 'last_post';

    const PERM_UPLOAD_ATTACHMENT = 'upload_attachment';
    const PERM_EDIT_TITLE = 'edit_title';
    const PERM_EDIT_TAGS = 'edit_tags';
    const PERM_POST = 'post';

    public function getMappings()
    {
        return [
            // xf_thread
            'thread_id' => self::KEY_ID,
            'title' => self::KEY_TITLE,
            'user_id' => self::KEY_CREATOR_USER_ID,
            'username' => self::KEY_CREATOR_USERNAME,
            'reply_count' => self::KEY_POST_COUNT,
            'view_count' => self::KEY_VIEW_COUNT,
            'post_date' => self::KEY_CREATE_DATE,
            'node_id' => self::KEY_FORUM_ID,
            'last_post_date' => self::KEY_UPDATE_DATE,

            self::DYNAMIC_KEY_IS_PUBLISHED,
            self::DYNAMIC_KEY_IS_DELETED,
            self::DYNAMIC_KEY_IS_STICKY,
            self::DYNAMIC_KEY_IS_FOLLOWED,
            self::DYNAMIC_KEY_FIRST_POST,
            self::DYNAMIC_KEY_PREFIXES,
            self::DYNAMIC_KEY_TAGS,
            self::DYNAMIC_KEY_POLL,
            self::DYNAMIC_KEY_USER_IS_IGNORED
        ];
    }

    public function calculateDynamicValue($key)
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->source;

        switch ($key) {
            case self::DYNAMIC_KEY_IS_PUBLISHED:
                return $thread->discussion_state === 'visible';
            case self::DYNAMIC_KEY_IS_DELETED:
                return $thread->discussion_state === 'deleted';
            case self::DYNAMIC_KEY_IS_STICKY:
                return $thread->sticky;
            case self::DYNAMIC_KEY_IS_FOLLOWED:
                $userId = \XF::visitor()->user_id;
                if ($userId < 1) {
                    return false;
                }

                return !empty($thread->Watch[$userId]);
            case self::DYNAMIC_KEY_FIRST_POST:
                return $this->transformer->transformEntity($this->selector, $thread->FirstPost);
            case self::DYNAMIC_KEY_PREFIXES:
                if (!$thread->prefix_id) {
                    return null;
                }

                if (!$thread->Prefix) {
                    return null;
                }

                return [$this->transformer->transformEntity($this->selector, $thread->Prefix)];
            case self::DYNAMIC_KEY_TAGS:
                return $this->transformer->transformTags($this, $thread->tags);
            case self::DYNAMIC_KEY_POLL:
                if (!$thread->Poll) {
                    return null;
                }

                return $this->transformer->transformEntity($this->selector, $thread->Poll);
            case self::DYNAMIC_KEY_USER_IS_IGNORED:
                return $thread->isIgnored();
        }

        return null;
    }

    public function collectPermissions()
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->source;

        $permissions = [
            self::PERM_EDIT => $thread->canEdit(),
            self::PERM_EDIT_TITLE => $thread->canEdit(),
            self::PERM_DELETE => $thread->canDelete(),

            self::PERM_POST => $thread->canReply(),
            self::PERM_EDIT_TAGS => $thread->canEditTags(),
            self::PERM_UPLOAD_ATTACHMENT => $thread->Forum->canUploadAndManageAttachments(),

            self::PERM_FOLLOW => $thread->canWatch()
        ];

        return $permissions;
    }

    public function collectLinks()
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->source;

        $links = [
            self::LINK_FORUM => $this->buildApiLink('forums', $thread->Forum),
            self::LINK_DETAIL => $this->buildApiLink('threads', $thread),
            self::LINK_PERMALINK => $this->buildApiLink('threads', $thread),

            self::LINK_POSTS => $this->buildApiLink('posts', null, ['thread_id' => $thread->thread_id]),

            self::LINK_FIRST_POSTER => $this->buildApiLink('users', $thread->FirstPost->User),
            self::LINK_FIRST_POSTER_AVATAR => $thread->FirstPost->User->getAvatarUrl('l'),
            self::LINK_FIRST_POST => $this->buildApiLink('posts', $thread->FirstPost),

            self::LINK_LAST_POSTER  => $this->buildApiLink('users', $thread->LastPoster),
            self::LINK_LAST_POST => $this->buildApiLink('posts', ['post_id' => $thread->last_post_id])
        ];

        return $links;
    }

    public function getFetchWith(array $extraWith = [])
    {
        $with = array_merge([
            'Forum',
            'Forum.Node',
            'User',
            'FirstPost'
        ], $extraWith);

        $userId = \XF::visitor()->user_id;
        if ($userId > 0) {
            $with[] = 'Read|' . $userId;
            $with[] = 'Watch|' . $userId;
            $with[] = 'FirstPost.Likes|' . $userId;
            $with[] = 'Forum.Node.Permissions|' . $userId;
        }

        return $with;
    }
}
