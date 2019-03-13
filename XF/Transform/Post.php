<?php

namespace Xfrocks\Api\XF\Transform;

use XF\Entity\Forum;
use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;
use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Api\Util\BackwardCompat21;
use Xfrocks\Api\Util\ParentFinder;

class Post extends AbstractHandler implements AttachmentParent
{
    const CONTENT_TYPE_POST = 'post';

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

    public function attachmentCalculateDynamicValue(TransformContext $context, $key)
    {
        switch ($key) {
            case self::ATTACHMENT__DYNAMIC_KEY_ID:
                return $context->getParentSourceValue('post_id');
        }

        return null;
    }

    public function attachmentCollectLinks(TransformContext $context, array &$links)
    {
        $post = $context->getParentSource();
        $links[self::ATTACHMENT__LINK_POST] = $this->buildApiLink('posts', $post);
    }

    public function attachmentCollectPermissions(TransformContext $context, array &$permissions)
    {
        /** @var \XF\Entity\Post $post */
        $post = $context->getParentSource();
        $canDelete = false;

        /** @var \XF\Entity\Thread|null $thread */
        $thread = $post->Thread;
        /** @var Forum|null $forum */
        $forum = $thread ? $thread->Forum : null;

        if ($forum && $forum->canUploadAndManageAttachments()) {
            $canDelete = $this->checkAttachmentCanManage(self::CONTENT_TYPE_POST, $post);
        }

        $permissions[self::PERM_DELETE] = $canDelete;
    }

    public function attachmentGetMappings(TransformContext $context, array &$mappings)
    {
        $mappings[] = self::ATTACHMENT__DYNAMIC_KEY_ID;
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var \XF\Entity\Post $post */
        $post = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_ATTACHMENTS:
                if ($post->attach_count < 1) {
                    return null;
                }

                return $this->transformer->transformEntityRelation($context, $key, $post, 'Attachments');
            case self::DYNAMIC_KEY_BODY_HTML:
                return $this->renderBbCodeHtml($key, $post->message, $post);
            case self::DYNAMIC_KEY_BODY_PLAIN:
                return $this->renderBbCodePlainText($post->message);
            case self::DYNAMIC_KEY_IS_DELETED:
                return $post->message_state === 'deleted';
            case self::DYNAMIC_KEY_IS_FIRST_POST:
                return $post->isFirstPost();
            case self::DYNAMIC_KEY_IS_IGNORED:
                if (\XF::visitor()->user_id === 0) {
                    return false;
                }

                return $post->isIgnored();
            case self::DYNAMIC_KEY_IS_LIKED:
                return BackwardCompat21::isLiked($post);
            case self::DYNAMIC_KEY_IS_PUBLISHED:
                return $post->message_state === 'visible';
            case self::DYNAMIC_KEY_SIGNATURE:
            case self::DYNAMIC_KEY_SIGNATURE_HTML:
            case self::DYNAMIC_KEY_SIGNATURE_PLAIN:
                if ($post->user_id < 1) {
                    return null;
                }

                $user = $post->User;
                if (!$user) {
                    return null;
                }

                $userProfile = $user->Profile;
                if (!$userProfile) {
                    return null;
                }

                switch ($key) {
                    case self::DYNAMIC_KEY_SIGNATURE:
                        return $userProfile->signature;
                    case self::DYNAMIC_KEY_SIGNATURE_HTML:
                        return $this->renderBbCodeHtml($key, $userProfile->signature, $user);
                    case self::DYNAMIC_KEY_SIGNATURE_PLAIN:
                        return $this->renderBbCodePlainText($userProfile->signature);
                }
        }

        return null;
    }

    public function collectPermissions(TransformContext $context)
    {
        /** @var \XF\Entity\Post $post */
        $post = $context->getSource();

        $permissions = [
            self::PERM_DELETE => $post->canDelete(),
            self::PERM_EDIT => $post->canEdit(),
            self::PERM_LIKE => BackwardCompat21::canLike($post),
            self::PERM_REPLY => $post->Thread->canReply(),
            self::PERM_REPORT => $post->canReport(),
            self::PERM_UPLOAD_ATTACHMENT => $post->Thread->Forum->canUploadAndManageAttachments(),
        ];

        return $permissions;
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var \XF\Entity\Post $post */
        $post = $context->getSource();

        $links = [
            self::LINK_ATTACHMENTS => $this->buildApiLink('posts/attachments', $post),
            self::LINK_DETAIL => $this->buildApiLink('posts', $post),
            self::LINK_LIKES => $this->buildApiLink('posts/likes', $post),
            self::LINK_PERMALINK => $this->buildPublicLink('posts', $post),
            self::LINK_REPORT => $this->buildApiLink('posts/report', $post),
            self::LINK_THREAD => $this->buildApiLink('threads', $post->Thread),
        ];

        if ($post->user_id > 0) {
            $user = $post->User;
            if ($user) {
                $links[self::LINK_POSTER] = $this->buildApiLink('users', $post->User);
                $links[self::LINK_POSTER_AVATAR] = $user->getAvatarUrl('l');
            }
        }

        return $links;
    }

    public function getMappings(TransformContext $context)
    {
        return [
            'attach_count' => self::KEY_ATTACHMENT_COUNT,
            'last_edit_date' => self::KEY_UPDATE_DATE,
            BackwardCompat21::getLikesColumn() => self::KEY_LIKE_COUNT,
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

    public function onTransformEntities(TransformContext $context, $entities)
    {
        $needAttachments = false;
        if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_ATTACHMENTS)) {
            $needAttachments = true;
        }
        if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_BODY_HTML)) {
            $needAttachments = true;
        }
        if ($needAttachments) {
            $this->enqueueEntitiesToAddAttachmentsTo($entities, self::CONTENT_TYPE_POST);
        }

        return $entities;
    }

    public function onTransformFinder(TransformContext $context, \XF\Mvc\Entity\Finder $finder)
    {
        $threadFinder = new ParentFinder($finder, 'Thread');
        $visitor = \XF::visitor();

        $threadFinder->with('Forum.Node.Permissions|' . $visitor->permission_combination_id);

        if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_SIGNATURE) ||
            !$context->selectorShouldExcludeField(self::DYNAMIC_KEY_SIGNATURE_HTML) ||
            !$context->selectorShouldExcludeField(self::DYNAMIC_KEY_SIGNATURE_PLAIN)
        ) {
            $finder->with('User.Profile');
        }

        $userId = $visitor->user_id;
        if ($userId > 0) {
            if (!$context->selectorShouldExcludeField(self::KEY_PERMISSIONS)) {
                $threadFinder->with('ReplyBans|' . $userId);
            }

            if (!$context->selectorShouldExcludeField(self::DYNAMIC_KEY_IS_LIKED)) {
                $finder->with(sprintf('%s|%d', BackwardCompat21::getLikesRelation(), $userId));
            }
        }

        return parent::onTransformFinder($context, $finder);
    }
}
