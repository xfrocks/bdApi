<?php

namespace Xfrocks\Api\XF\Transform;

use XF\Entity\Forum;
use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;

class Post extends AbstractHandler implements AttachmentParent
{
    const ATTACHMENT__DYNAMIC_KEY_ID = 'post_id';
    const ATTACHMENT__LINK_POST = 'post';

    const KEY_ATTACHMENT_COUNT = 'post_attachment_count';
    const KEY_BODY = 'post_body';
    const KEY_CREATE_DATE = 'post_create_date';
    const KEY_ID = 'post_id';
    const KEY_LIKE_COUNT = 'post_like_count';
    const KEY_UPDATE_DATE = 'post_update_date';
    const KEY_POSTER_USER_ID = 'poster_user_id';
    const KEY_POSTER_USERNAME = 'poster_username';
    const KEY_THREAD_ID = 'thread_id';

    const DYNAMIC_KEY_IS_LIKED = 'post_is_liked';
    const DYNAMIC_KEY_BODY_HTML = 'post_body_html';
    const DYNAMIC_KEY_BODY_PLAIN = 'post_body_plain_text';
    const DYNAMIC_KEY_IS_PUBLISHED = 'post_is_published';
    const DYNAMIC_KEY_IS_DELETED = 'post_is_deleted';
    const DYNAMIC_KEY_IS_FIRST_POST = 'post_is_first_post';
    const DYNAMIC_KEY_SIGNATURE = 'signature';
    const DYNAMIC_KEY_SIGNATURE_HTML = 'signature_html';
    const DYNAMIC_KEY_SIGNATURE_PLAIN = 'signature_plain_text';
    const DYNAMIC_KEY_IS_IGNORED = 'user_is_ignored';

    const LINK_THREAD = 'thread';
    const LINK_POSTER = 'poster';
    const LINK_POSTER_AVATAR = 'poster_avatar';

    const PERM_REPLY = 'reply';
    const PERM_UPLOAD_ATTACHMENT = 'upload_attachment';

    protected $attachmentData = null;

    public function attachmentCalculateDynamicValue($attachmentHandler, $key)
    {
        switch ($key) {
            case self::ATTACHMENT__DYNAMIC_KEY_ID:
                return $this->source['post_id'];
        }

        return null;
    }

    public function attachmentCollectLinks($attachmentHandler, array &$links)
    {
        $links[self::ATTACHMENT__LINK_POST] = $this->buildApiLink('posts', $this->source);
    }

    public function attachmentCollectPermissions($attachmentHandler, array &$permissions)
    {
        /** @var \XF\Entity\Post $post */
        $post = $this->source;
        $canDelete = false;

        /** @var \XF\Entity\Thread|null $thread */
        $thread = $post->Thread;
        /** @var Forum|null $forum */
        $forum = $thread ? $thread->Forum : null;

        if ($forum && $forum->canUploadAndManageAttachments()) {
            $attachmentData = $this->getAttachmentData();
            /** @var \XF\Attachment\AbstractHandler $attachmentHandler */
            $attachmentHandler = $attachmentData['handler'];
            $canDelete = $attachmentHandler->canManageAttachments($attachmentData['context']);
        }

        $permissions[self::PERM_DELETE] = $canDelete;
    }

    public function attachmentGetMappings($attachmentHandler, array &$mappings)
    {
        $mappings[] = self::ATTACHMENT__DYNAMIC_KEY_ID;
    }

    public function calculateDynamicValue($key)
    {
        /** @var \XF\Entity\Post $post */
        $post = $this->source;

        switch ($key) {
            case self::DYNAMIC_KEY_ATTACHMENTS:
                if (!$post->attach_count) {
                    return null;
                }
                $attachments = $this->getAttachmentData();
                return $this->transformer->transformSubEntities($this, $key, $attachments['attachments']);
            case self::DYNAMIC_KEY_BODY_HTML:
                return $this->renderBbCodeHtml($key, $post->message);
            case self::DYNAMIC_KEY_BODY_PLAIN:
                return $this->renderBbCodePlainText($post->message);
            case self::DYNAMIC_KEY_IS_DELETED:
                return $post->message_state === 'deleted';
            case self::DYNAMIC_KEY_IS_FIRST_POST:
                return $post->isFirstPost();
            case self::DYNAMIC_KEY_IS_IGNORED:
                if (!\XF::visitor()->user_id) {
                    return false;
                }

                return $post->isIgnored();
            case self::DYNAMIC_KEY_IS_LIKED:
                return $post->isLiked();
            case self::DYNAMIC_KEY_IS_PUBLISHED:
                return $post->message_state === 'visible';
            case self::DYNAMIC_KEY_SIGNATURE:
                return $post->User->Profile->signature;
            case self::DYNAMIC_KEY_SIGNATURE_HTML:
                return $this->renderBbCodeHtml($key, $post->User->Profile->signature);
            case self::DYNAMIC_KEY_SIGNATURE_PLAIN:
                return $this->renderBbCodePlainText($post->User->Profile->signature);
        }

        return null;
    }

    public function collectPermissions()
    {
        /** @var \XF\Entity\Post $post */
        $post = $this->source;

        $permissions = [
            self::PERM_DELETE => $post->canDelete(),
            self::PERM_EDIT => $post->canEdit(),
            self::PERM_LIKE => $post->canLike(),
            self::PERM_REPLY => $post->Thread->canReply(),
            self::PERM_REPORT => $post->canReport(),
            self::PERM_UPLOAD_ATTACHMENT => $post->Thread->Forum->canUploadAndManageAttachments(),
        ];

        return $permissions;
    }

    public function collectLinks()
    {
        /** @var \XF\Entity\Post $post */
        $post = $this->source;

        $links = [
            self::LINK_ATTACHMENTS => $this->buildApiLink('posts/attachments', $post),
            self::LINK_DETAIL => $this->buildApiLink('posts', $post),
            self::LINK_LIKES => $this->buildApiLink('posts/likes', $post),
            self::LINK_PERMALINK => $this->buildApiLink('posts', $post),
            self::LINK_POSTER => $this->buildApiLink('users', $post->User),
            self::LINK_POSTER_AVATAR => $post->User->getAvatarUrl('l'),
            self::LINK_REPORT => $this->buildApiLink('posts/report', $post),
            self::LINK_THREAD => $this->buildApiLink('threads', $post->Thread),
        ];

        return $links;
    }

    public function getFetchWith(array $extraWith = [])
    {
        $with = array_merge([
            'User',
            'User.Profile',
            'User.Privacy',
            'Thread',
            'Thread.Forum.Node'
        ], $extraWith);

        $userId = \XF::visitor()->user_id;
        if ($userId > 0) {
            $with[] = 'Thread.Forum.Node.Permissions|' . $userId;
            $with[] = 'Likes|' . $userId;
        }

        return $with;
    }

    public function getMappings()
    {
        return [
            'attach_count' => self::KEY_ATTACHMENT_COUNT,
            'last_edit_date' => self::KEY_UPDATE_DATE,
            'likes' => self::KEY_LIKE_COUNT,
            'message' => self::KEY_BODY,
            'post_date' => self::KEY_CREATE_DATE,
            'post_id' => self::KEY_ID,
            'thread_id' => self::KEY_THREAD_ID,
            'user_id' => self::KEY_POSTER_USER_ID,
            'username' => self::KEY_POSTER_USERNAME,

            self::DYNAMIC_KEY_ATTACHMENTS,
            self::DYNAMIC_KEY_BODY_HTML,
            self::DYNAMIC_KEY_BODY_PLAIN,
            self::DYNAMIC_KEY_IS_DELETED,
            self::DYNAMIC_KEY_IS_FIRST_POST,
            self::DYNAMIC_KEY_IS_IGNORED,
            self::DYNAMIC_KEY_IS_LIKED,
            self::DYNAMIC_KEY_IS_PUBLISHED,
            self::DYNAMIC_KEY_SIGNATURE,
            self::DYNAMIC_KEY_SIGNATURE_HTML,
            self::DYNAMIC_KEY_SIGNATURE_PLAIN,
        ];
    }

    /**
     * @return array
     */
    protected function getAttachmentData()
    {
        static $contentType = 'post';

        /** @var \XF\Entity\Post $post */
        $post = $this->source;

        if (!isset($this->attachmentData[$post->post_id])) {
            /** @var \XF\Repository\Attachment $attachmentRepo */
            $attachmentRepo = $this->app->repository('XF:Attachment');
            $this->attachmentData[$post->post_id] = $attachmentRepo->getEditorData($contentType, $post);
            $this->attachmentData[$post->post_id]['handler'] = $attachmentRepo->getAttachmentHandler($contentType);
        }

        return $this->attachmentData[$post->post_id];
    }
}
