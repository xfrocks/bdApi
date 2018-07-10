<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;

class Thread extends AbstractHandler implements AttachmentParent
{
    const ATTACHMENT__DYNAMIC_KEY_ID = 'thread_id';
    const ATTACHMENT__LINK_THREAD = 'thread';

    const KEY_ID = 'thread_id';
    const KEY_TITLE = 'thread_title';
    const KEY_CREATOR_USER_ID = 'creator_user_id';
    const KEY_CREATOR_USERNAME = 'creator_username';
    const KEY_POST_COUNT = 'thread_post_count';
    const KEY_VIEW_COUNT = 'thread_view_count';
    const KEY_CREATE_DATE = 'thread_create_date';
    const KEY_FORUM_ID = 'forum_id';
    const KEY_UPDATE_DATE  = 'thread_update_date';

    const DYNAMIC_KEY_IS_PUBLISHED = 'thread_is_published';
    const DYNAMIC_KEY_IS_DELETED = 'thread_is_deleted';
    const DYNAMIC_KEY_IS_STICKY = 'thread_is_sticky';
    const DYNAMIC_KEY_IS_FOLLOWED = 'thread_is_followed';
    const DYNAMIC_KEY_FIRST_POST = 'first_post';
//    const DYNAMIC_KEY_LAST_POST = 'last_post';
    const DYNAMIC_KEY_PREFIXES = 'thread_prefixes';
    const DYNAMIC_KEY_TAGS = 'tgread_tags';
    const DYNAMIC_KEY_POLL = 'poll';

    const LINK_FORUM = 'forum';
    const LINK_CREATOR_AVATAR = 'creator_avatar';

    protected $attachmentData = null;

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
            self::DYNAMIC_KEY_POLL
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
                return $this->transformer->transformEntity($this->selector, $thread->Prefix);
            case self::DYNAMIC_KEY_TAGS:
                return $this->transformer->transformTags($this, $thread->tags);
            case self::DYNAMIC_KEY_POLL:
                return $this->transformer->transformEntity($this->selector, $thread->Poll);
        }

        return null;
    }

    public function collectPermissions()
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->source;

        $permissions = [
            self::PERM_EDIT => $thread->canEdit(),
            self::PERM_LIKE => $thread->FirstPost->canLike(),
            self::PERM_REPORT => $thread->FirstPost->canReport(),
            self::PERM_DELETE => $thread->canDelete(),
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
            self::LINK_CREATOR_AVATAR => $thread->User->getAvatarUrl('l'),

            self::LINK_DETAIL => $this->buildApiLink('threads', $thread),
            self::LINK_PERMALINK => $this->buildApiLink('threads', $thread),

            self::LINK_REPORT => $this->buildApiLink('posts/report', $thread->FirstPost),
            self::LINK_LIKES => $this->buildApiLink('posts/likes', $thread->FirstPost),

            self::LINK_FOLLOWERS => $this->buildApiLink('threads/followers', $thread)
        ];

        if ($thread->FirstPost->attach_count > 0) {
            $links[self::LINK_ATTACHMENTS] = $this->buildApiLink('threads/attachments', $thread);
        }

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

    public function attachmentCalculateDynamicValue($attachmentHandler, $key)
    {
        switch ($key) {
            case self::ATTACHMENT__DYNAMIC_KEY_ID:
                return $this->source['thread_id'];
        }

        return null;
    }

    public function attachmentCollectPermissions($attachmentHandler, array &$permissions)
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->source;
        $canDelete = false;

        if ($thread->canEdit() && $thread->Forum->canUploadAndManageAttachments()) {
            $attachmentData = $this->getAttachmentData();
            /** @var \XF\Attachment\AbstractHandler $attachmentHandler */
            $attachmentHandler = $attachmentData['handler'];
            $canDelete = $attachmentHandler->canManageAttachments($attachmentData['context']);
        }

        $permissions[self::PERM_DELETE] = $canDelete;
    }

    public function attachmentCollectLinks($attachmentHandler, array &$links)
    {
        $links[self::ATTACHMENT__LINK_THREAD] = $this->buildApiLink('threads', $this->source);
    }

    public function attachmentGetMappings($attachmentHandler, array &$mappings)
    {
        $mappings[] = self::ATTACHMENT__DYNAMIC_KEY_ID;
    }

    protected function getAttachmentData()
    {
        static $contentType = 'post';

        /** @var \XF\Entity\Thread $thread */
        $thread = $this->source;

        if (!isset($this->attachmentData[$thread->thread_id])) {
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->app->repository('XF:Attachment');
            $this->attachmentData[$thread->thread_id] = $attachmentRepo->getEditorData(
                $contentType,
                $thread->FirstPost
            );
            $this->attachmentData[$thread->thread_id]['handler'] = $attachmentRepo->getAttachmentHandler($contentType);
        }

        return $this->attachmentData[$thread->thread_id];
    }
}
