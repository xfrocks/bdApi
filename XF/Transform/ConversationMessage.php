<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;
use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Api\Util\BackwardCompat21;

class ConversationMessage extends AbstractHandler implements AttachmentParent
{
    const ATTACHMENT__DYNAMIC_KEY_ID = 'message_id';
    const ATTACHMENT__LINK_MESSAGE = 'conversation-messages';

    const CONTENT_TYPE_CONVO_MESSAGE = 'conversation_message';

    const KEY_ID = 'message_id';
    const KEY_CREATOR_USER_ID = 'creator_user_id';
    const KEY_CREATOR_USERNAME = 'creator_username';
    const KEY_CREATE_DATE = 'message_create_date';
    const KEY_CONVERSATION_ID = 'conversation_id';
    const KEY_ATTACH_COUNT = 'message_attach_count';

    const DYNAMIC_KEY_BODY = 'message_body';
    const DYNAMIC_KEY_BODY_HTML = 'message_body_html';
    const DYNAMIC_KEY_BODY_PLAIN = 'message_body_plain_text';
    const DYNAMIC_KEY_IS_LIKED = 'message_is_liked';
    const DYNAMIC_KEY_SIGNATURE = 'signature';
    const DYNAMIC_KEY_SIGNATURE_HTML = 'signature_html';
    const DYNAMIC_KEY_SIGNATURE_PLAIN = 'signature_plain_text';
    const DYNAMIC_KEY_USER_IS_IGNORED = 'user_is_ignored';

    const PERM_REPLY = 'reply';
    const PERM_UPLOAD_ATTACHMENT = 'upload_attachment';

    const LINK_CONVERSATION = 'conversation';
    const LINK_CREATOR = 'creator';
    const LINK_CREATOR_AVATAR = 'creator_avatar';

    public function attachmentCalculateDynamicValue(TransformContext $context, $key)
    {
        switch ($key) {
            case self::ATTACHMENT__DYNAMIC_KEY_ID:
                return $context->getParentSourceValue('message_id');
        }

        return null;
    }

    public function attachmentCollectPermissions(TransformContext $context, array &$permissions)
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $context->getParentSource();

        $canDelete = false;
        /** @var \XF\Entity\ConversationMaster|null $conversation */
        $conversation = $message->Conversation;

        if ($conversation && $conversation->canUploadAndManageAttachments()) {
            $canDelete = $this->checkAttachmentCanManage(self::CONTENT_TYPE_CONVO_MESSAGE, $message);
        }

        $permissions[self::PERM_DELETE] = $canDelete;
    }

    public function attachmentCollectLinks(TransformContext $context, array &$links)
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $context->getParentSource();

        $links[self::ATTACHMENT__LINK_MESSAGE] = $this->buildApiLink('conversation-messages', $message);
    }

    public function attachmentGetMappings(TransformContext $context, array &$mappings)
    {
        $mappings[] = self::ATTACHMENT__DYNAMIC_KEY_ID;
    }

    public function getMappings(TransformContext $context)
    {
        return [
            'message_id' => self::KEY_ID,
            'conversation_id' => self::KEY_CONVERSATION_ID,
            'user_id' => self::KEY_CREATOR_USER_ID,
            'username' => self::KEY_CREATOR_USERNAME,
            'message_date' => self::KEY_CREATE_DATE,
            'attach_count' => self::KEY_ATTACH_COUNT,

            self::DYNAMIC_KEY_ATTACHMENTS,
            self::DYNAMIC_KEY_BODY,
            self::DYNAMIC_KEY_BODY_HTML,
            self::DYNAMIC_KEY_BODY_PLAIN,
            self::DYNAMIC_KEY_SIGNATURE,
            self::DYNAMIC_KEY_SIGNATURE_HTML,
            self::DYNAMIC_KEY_SIGNATURE_PLAIN,
            self::DYNAMIC_KEY_USER_IS_IGNORED
        ];
    }

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_ATTACHMENTS:
                if ($message->attach_count < 1) {
                    return null;
                }

                return $this->transformer->transformEntityRelation($context, $key, $message, 'Attachments');
            case self::DYNAMIC_KEY_BODY:
                return $message->message;
            case self::DYNAMIC_KEY_BODY_HTML:
                return $this->renderBbCodeHtml($key, $message->message, $message);
            case self::DYNAMIC_KEY_BODY_PLAIN:
                return $this->renderBbCodePlainText($message->message);
            case self::DYNAMIC_KEY_IS_LIKED:
                return BackwardCompat21::isLiked($message);
            case self::DYNAMIC_KEY_SIGNATURE:
                if ($message->user_id < 1) {
                    return null;
                }

                /** @var \XF\Entity\User|null $user */
                $user = $message->User;
                if (!$user) {
                    return null;
                }

                return $user->Profile->signature;
            case self::DYNAMIC_KEY_SIGNATURE_HTML:
                if ($message->user_id < 1) {
                    return null;
                }

                /** @var \XF\Entity\User|null $user */
                $user = $message->User;
                if (!$user) {
                    return null;
                }

                return $this->renderBbCodeHtml($key, $user->Profile->signature, $user->Profile);
            case self::DYNAMIC_KEY_SIGNATURE_PLAIN:
                if ($message->user_id < 1) {
                    return null;
                }

                /** @var \XF\Entity\User|null $user */
                $user = $message->User;
                if (!$user) {
                    return null;
                }

                return $this->renderBbCodePlainText($user->Profile->signature);
            case self::DYNAMIC_KEY_USER_IS_IGNORED:
                return $message->isIgnored();
        }

        return null;
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $context->getSource();
        /** @var \XF\Entity\User|null $user */
        $user = $message->User;

        $links = [
            self::LINK_DETAIL => $this->buildApiLink('conversation-messages', $message),
            self::LINK_CONVERSATION => $this->buildApiLink('conversations', $message->Conversation),
            self::LINK_REPORT => $this->buildApiLink('conversation-messages/report', $message)
        ];

        if ($user) {
            $links[self::LINK_CREATOR] = $this->buildApiLink('users', $user);
            $links[self::LINK_CREATOR_AVATAR] = $user->getAvatarUrl('l');
        }

        return $links;
    }

    public function collectPermissions(TransformContext $context)
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $context->getSource();

        $perms = [
            self::PERM_VIEW => $message->canView(),
            self::PERM_EDIT => $message->canEdit(),

            // save value for version of xenforo 1.x
            self::PERM_DELETE => true,

            self::PERM_REPLY => $message->Conversation->canReply(),
            self::PERM_UPLOAD_ATTACHMENT => $message->Conversation->canUploadAndManageAttachments(),

            self::PERM_LIKE => BackwardCompat21::canLike($message),
        ];

        return $perms;
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
            $this->enqueueEntitiesToAddAttachmentsTo($entities, self::CONTENT_TYPE_CONVO_MESSAGE);
        }

        return parent::onTransformEntities($context, $entities);
    }
}
