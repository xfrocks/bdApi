<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class Forum extends AbstractHandler
{
    const KEY_DESCRIPTION = 'forum_description';
    const KEY_ID = 'forum_id';
    const KEY_TITLE = 'forum_title';
    const KEY_POST_COUNT = 'forum_post_count';
    const KEY_THREAD_COUNT = 'forum_thread_count';
    const KEY_DEFAULT_THREAD_PREFIX_ID = 'thread_default_prefix_id';
    const KEY_PREFIX_IS_REQUIRED = 'thread_prefix_is_required';

    const DYNAMIC_KEY_IS_FOLLOW = 'forum_is_follow';
    const DYNAMIC_KEY_PREFIXES = 'forum_prefixes';

    const LINK_SUB_CATEGORIES = 'sub-categories';
    const LINK_SUB_FORUMS = 'sub-forums';
    const LINK_THREADS = 'threads';

    const PERM_CREATE_THREAD = 'create_thread';
    const PERM_UPLOAD_ATTACHMENT = 'upload_attachment';

    public function getMappings()
    {
        return [
            'node_id' => self::KEY_ID,
            'title' => self::KEY_TITLE,
            'description' => self::KEY_DESCRIPTION,
            'discussion_count' => self::KEY_THREAD_COUNT,
            'message_count' => self::KEY_POST_COUNT,
            'default_prefix_id' => self::KEY_DEFAULT_THREAD_PREFIX_ID,
            'require_prefix' => self::KEY_PREFIX_IS_REQUIRED,

            self::DYNAMIC_KEY_IS_FOLLOW,
            self::DYNAMIC_KEY_PREFIXES
        ];
    }

    public function collectLinks()
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->source;

        $links = [
            self::LINK_PERMALINK => $this->buildApiLink('forums', $forum),
            self::LINK_DETAIL => $this->buildApiLink('forums', $forum),

            self::LINK_FOLLOWERS => $this->buildApiLink('forums/followers', $forum),
            self::LINK_SUB_CATEGORIES => $this->buildApiLink('forums', null, ['parent_category_id' => $forum->node_id]),
            self::LINK_SUB_FORUMS => $this->buildApiLink('forums', null, ['parent_forum_id' => $forum->node_id]),

            self::LINK_THREADS => $this->buildApiLink('threads', null, ['forum_id' => $forum->node_id])
        ];

        return $links;
    }

    public function collectPermissions()
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->source;

        $perms = [
            self::PERM_FOLLOW => $forum->canWatch(),
            self::PERM_CREATE_THREAD => $forum->canCreateThread(),
            self::PERM_UPLOAD_ATTACHMENT => $forum->canUploadAndManageAttachments()
        ];

        return $perms;
    }

    public function calculateDynamicValue($key)
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->source;

        switch ($key) {
            case self::DYNAMIC_KEY_IS_FOLLOW:
                $visitor = \XF::visitor();
                if ($visitor->user_id <= 0) {
                    return false;
                }

                return !empty($forum->Watch[$visitor->user_id]);
            case self::DYNAMIC_KEY_PREFIXES:
                if (!$forum->prefixes) {
                    return null;
                }

                /** @var \XF\Entity\ThreadPrefix[] $prefixes */
                $prefixes = $forum->prefixes;

                return $this->transformer->transformSubEntities($this, $key, $prefixes);
        }

        return null;
    }
}