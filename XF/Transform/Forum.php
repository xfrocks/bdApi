<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\TransformContext;

class Forum extends AbstractNode
{
    const KEY_POST_COUNT = 'forum_post_count';
    const KEY_THREAD_COUNT = 'forum_thread_count';
    const KEY_DEFAULT_THREAD_PREFIX_ID = 'thread_default_prefix_id';
    const KEY_PREFIX_IS_REQUIRED = 'thread_prefix_is_required';

    const DYNAMIC_KEY_IS_FOLLOW = 'forum_is_follow';
    const DYNAMIC_KEY_PREFIXES = 'forum_prefixes';

    const LINK_THREADS = 'threads';

    const PERM_CREATE_THREAD = 'create_thread';
    const PERM_UPLOAD_ATTACHMENT = 'upload_attachment';

    public function getMappings(TransformContext $context)
    {
        $mappings = parent::getMappings($context);

        $mappings += [
            'discussion_count' => self::KEY_THREAD_COUNT,
            'message_count' => self::KEY_POST_COUNT,
            'default_prefix_id' => self::KEY_DEFAULT_THREAD_PREFIX_ID,
            'require_prefix' => self::KEY_PREFIX_IS_REQUIRED,

            self::DYNAMIC_KEY_IS_FOLLOW,
            self::DYNAMIC_KEY_PREFIXES
        ];

        return $mappings;
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var array $links */
        $links = parent::collectLinks($context);
        /** @var \XF\Entity\Forum $forum */
        $forum = $context->getSource();

        $links += [
            self::LINK_FOLLOWERS => $this->buildApiLink('forums/followers', $forum),
            self::LINK_THREADS => $this->buildApiLink('threads', null, ['forum_id' => $forum->node_id])
        ];

        return $links;
    }

    public function collectPermissions(TransformContext $context)
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $context->getSource();
        /** @var array $perms */
        $perms = parent::collectPermissions($context);

        $perms += [
            self::PERM_FOLLOW => $forum->canWatch(),
            self::PERM_CREATE_THREAD => $forum->canCreateThread(),
            self::PERM_UPLOAD_ATTACHMENT => $forum->canUploadAndManageAttachments()
        ];

        return $perms;
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_IS_FOLLOW:
                $userId = \XF::visitor()->user_id;
                if ($userId < 1) {
                    return false;
                }

                return isset($forum->Watch[$userId]);
            case self::DYNAMIC_KEY_PREFIXES:
                if (count($forum->prefix_cache) === 0) {
                    return null;
                }

                $finder = $forum->finder('XF:ThreadPrefix')
                    ->where('prefix_id', $forum->prefix_cache)
                    ->order('materialized_order');

                return $this->transformer->transformFinder($context, $key, $finder);
        }

        return parent::calculateDynamicValue($context, $key);
    }

    protected function getNameSingular()
    {
        return 'forum';
    }

    protected function getRoutePrefix()
    {
        return 'forums';
    }
}
