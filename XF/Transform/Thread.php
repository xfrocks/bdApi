<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class Thread extends AbstractHandler
{
    const KEY_CREATOR_USER_ID = 'creator_user_id';
    const KEY_CREATOR_USERNAME = 'creator_username';
    const KEY_CREATE_DATE = 'thread_create_date';
    const KEY_FORUM_ID = 'forum_id';
    const KEY_ID = 'thread_id';
    const KEY_POST_COUNT = 'thread_post_count';
    const KEY_TITLE = 'thread_title';
    const KEY_UPDATE_DATE = 'thread_update_date';
    const KEY_VIEW_COUNT = 'thread_view_count';

    const DYNAMIC_KEY_FIRST_POST = 'first_post';
    const DYNAMIC_KEY_IS_DELETED = 'thread_is_deleted';
    const DYNAMIC_KEY_IS_FOLLOWED = 'thread_is_followed';
    const DYNAMIC_KEY_IS_PUBLISHED = 'thread_is_published';
    const DYNAMIC_KEY_IS_STICKY = 'thread_is_sticky';
    const DYNAMIC_KEY_PREFIXES = 'thread_prefixes';
    const DYNAMIC_KEY_POLL = 'poll';
    const DYNAMIC_KEY_TAGS = 'thread_tags';
    const DYNAMIC_KEY_USER_IS_IGNORED = 'user_is_ignored';

    const LINK_FIRST_POST = 'first_post';
    const LINK_FIRST_POSTER = 'first_poster';
    const LINK_FIRST_POSTER_AVATAR = 'first_poster_avatar';
    const LINK_FORUM = 'forum';
    const LINK_LAST_POST = 'last_post';
    const LINK_LAST_POSTER = 'last_poster';
    const LINK_POSTS = 'posts';

    const PERM_EDIT_TITLE = 'edit_title';
    const PERM_EDIT_TAGS = 'edit_tags';
    const PERM_POST = 'post';
    const PERM_UPLOAD_ATTACHMENT = 'upload_attachment';

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_FIRST_POST:
                return $this->transformer->transformEntity($context, $key, $thread->FirstPost);
            case self::DYNAMIC_KEY_IS_DELETED:
                return $thread->discussion_state === 'deleted';
            case self::DYNAMIC_KEY_IS_FOLLOWED:
                $userId = \XF::visitor()->user_id;
                if ($userId < 1) {
                    return false;
                }

                return !empty($thread->Watch[$userId]);
            case self::DYNAMIC_KEY_IS_PUBLISHED:
                return $thread->discussion_state === 'visible';
            case self::DYNAMIC_KEY_IS_STICKY:
                return $thread->sticky;
            case self::DYNAMIC_KEY_POLL:
                if ($thread->discussion_type !== 'poll') {
                    return null;
                }

                /** @var \XF\Entity\Poll|null $poll */
                $poll = $thread->Poll;
                if (!$poll) {
                    return null;
                }

                return $this->transformer->transformEntity($context, $key, $thread->Poll);
            case self::DYNAMIC_KEY_PREFIXES:
                if (!$thread->prefix_id) {
                    return null;
                }

                /** @var \XF\Entity\ThreadPrefix|null $prefix */
                $prefix = $thread->Prefix;
                if (!$prefix) {
                    return null;
                }

                $prefixData = $this->transformer->transformEntity($context, $key, $prefix);
                if (count($prefixData) === 0) {
                    return null;
                }

                return [$prefixData];
            case self::DYNAMIC_KEY_TAGS:
                return $this->transformer->transformTags($context, $thread->tags);
            case self::DYNAMIC_KEY_USER_IS_IGNORED:
                return $thread->isIgnored();
        }

        return null;
    }

    public function collectPermissions($context)
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $context->getSource();

        $permissions = [
            self::PERM_DELETE => $thread->canDelete(),
            self::PERM_EDIT => $thread->canEdit(),
            self::PERM_EDIT_TAGS => $thread->canEditTags(),
            self::PERM_EDIT_TITLE => $thread->canEdit(),
            self::PERM_FOLLOW => $thread->canWatch(),
            self::PERM_POST => $thread->canReply(),
            self::PERM_UPLOAD_ATTACHMENT => $thread->Forum->canUploadAndManageAttachments(),
        ];

        return $permissions;
    }

    public function collectLinks($context)
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $context->getSource();

        $links = [
            self::LINK_DETAIL => $this->buildApiLink('threads', $thread),
            self::LINK_FIRST_POST => $this->buildApiLink('posts', $thread->FirstPost),
            self::LINK_FORUM => $this->buildApiLink('forums', $thread->Forum),
            self::LINK_LAST_POSTER => $this->buildApiLink('users', $thread->LastPoster),
            self::LINK_LAST_POST => $this->buildApiLink('posts', ['post_id' => $thread->last_post_id]),
            self::LINK_PERMALINK => $this->buildApiLink('threads', $thread),
            self::LINK_POSTS => $this->buildApiLink('posts', null, ['thread_id' => $thread->thread_id]),
        ];

        $firstPost = $thread->FirstPost;
        if ($firstPost->user_id > 0) {
            $firstPostUser = $firstPost->User;
            if (!empty($firstPostUser)) {
                $links[self::LINK_FIRST_POSTER] = $this->buildApiLink('users', $firstPostUser);
                $links[self::LINK_FIRST_POSTER_AVATAR] = $firstPostUser->getAvatarUrl('l');
            }
        }

        return $links;
    }

    public function getExtraWith()
    {
        $with = [
            'Forum',
            'Forum.Node',
            'User',
            'FirstPost',
        ];

        $userId = \XF::visitor()->user_id;
        if ($userId > 0) {
            $with = array_merge($with, [
                'FirstPost.Likes|' . $userId,
                'Forum.Node.Permissions|' . $userId,
                'Read|' . $userId,
                'ReplyBans|' . $userId,
                'Watch|' . $userId,
            ]);
        }

        return $with;
    }

    public function getMappings($context)
    {
        return [
            // xf_thread
            'last_post_date' => self::KEY_UPDATE_DATE,
            'node_id' => self::KEY_FORUM_ID,
            'post_date' => self::KEY_CREATE_DATE,
            'reply_count' => self::KEY_POST_COUNT,
            'thread_id' => self::KEY_ID,
            'title' => self::KEY_TITLE,
            'user_id' => self::KEY_CREATOR_USER_ID,
            'username' => self::KEY_CREATOR_USERNAME,
            'view_count' => self::KEY_VIEW_COUNT,

            self::DYNAMIC_KEY_IS_PUBLISHED,
            self::DYNAMIC_KEY_IS_DELETED,
            self::DYNAMIC_KEY_IS_STICKY,
            self::DYNAMIC_KEY_IS_FOLLOWED,
            self::DYNAMIC_KEY_FIRST_POST,
            self::DYNAMIC_KEY_POLL,
            self::DYNAMIC_KEY_PREFIXES,
            self::DYNAMIC_KEY_TAGS,
            self::DYNAMIC_KEY_USER_IS_IGNORED,
        ];
    }

    public function onTransformEntities($context, $entities)
    {
        if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_FIRST_POST)) {
            $postTransformer = $this->transformer->handler('XF:Post');

            $firstPosts = [];
            /** @var \XF\Entity\Thread $thread */
            foreach ($entities as $thread) {
                $firstPosts[] = $thread->FirstPost;
            }

            $subContext = $context->getSubContext(self::DYNAMIC_KEY_FIRST_POST, null, null);
            $postTransformer->onTransformEntities($subContext, $firstPosts);
        }

        return $entities;
    }
}
