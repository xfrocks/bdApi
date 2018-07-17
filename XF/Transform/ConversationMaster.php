<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class ConversationMaster extends AbstractHandler
{
    const KEY_CREATE_DATE = 'conversation_create_date';
    const KEY_ID = 'conversation_id';
    const KEY_MESSAGE_COUNT = 'conversation_message_count';
    const KEY_TITLE = 'conversation_title';
    const KEY_CREATOR_USER_ID = 'creator_user_id';
    const KEY_CREATOR_USERNAME = 'creator_username';
    const KEY_UPDATE_DATE = 'conversation_update_date';

    const DYNAMIC_KEY_IS_DELETED = 'conversation_is_deleted';
    const DYNAMIC_KEY_IS_IGNORED = 'user_is_ignored';
    const DYNAMIC_KEY_IS_OPEN = 'conversation_is_open';
    const DYNAMIC_KEY_FIRST_MESSAGE = 'first_message';
    const DYNAMIC_KEY_HAS_NEW_MESSAGE = 'conversation_has_new_message';
    const DYNAMIC_KEY_LAST_MESSAGE = 'last_message';
    const DYNAMIC_KEY_RECIPIENTS = 'recipients';

    const LINK_MESSAGES = 'messages';

    const PERM_REPLY = 'reply';
    const PERM_UPLOAD_ACTTACHMENT = 'upload_attachment';

    public function getMappings($context)
    {
        return [
            'start_date' => self::KEY_CREATE_DATE,
            'conversation_id' => self::KEY_ID,
            'reply_count' => self::KEY_MESSAGE_COUNT,
            'title' => self::KEY_TITLE,
            'user_id' => self::KEY_CREATOR_USER_ID,
            'username' => self::KEY_CREATOR_USERNAME,
            'last_message_date' => self::KEY_UPDATE_DATE,

            self::DYNAMIC_KEY_IS_DELETED,
            self::DYNAMIC_KEY_IS_IGNORED,
            self::DYNAMIC_KEY_IS_OPEN,
            self::DYNAMIC_KEY_FIRST_MESSAGE,
            self::DYNAMIC_KEY_HAS_NEW_MESSAGE,
            self::DYNAMIC_KEY_LAST_MESSAGE,
            self::DYNAMIC_KEY_RECIPIENTS
        ];
    }

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_IS_DELETED:
                $visitor = \XF::visitor();
                if (empty($conversation->Recipients[$visitor->user_id])) {
                    return false;
                }

                $recipient = $conversation->Recipients[$visitor->user_id];
                switch ($recipient->recipient_state) {
                    case 'active':
                        return false;
                    case 'deleted':
                    case 'deleted_ignored':
                        return true;
                    default:
                        return null;
                }
            case self::DYNAMIC_KEY_IS_IGNORED:
                return \XF::visitor()->isIgnoring($conversation->user_id);
            case self::DYNAMIC_KEY_IS_OPEN:
                $visitor = \XF::visitor();
                if (empty($conversation->Recipients[$visitor->user_id])) {
                    return true;
                }

                $recipient = $conversation->Recipients[$visitor->user_id];
                switch ($recipient->recipient_state) {
                    case 'active':
                        return $conversation->conversation_open;
                    case 'deleted':
                    case 'deleted_ignored':
                        return false;
                    default:
                        return null;
                }
            case self::DYNAMIC_KEY_FIRST_MESSAGE:
                return $this->transformer->transformEntity($context, $key, $conversation->FirstMessage);
            case self::DYNAMIC_KEY_HAS_NEW_MESSAGE:
                $visitor = \XF::visitor();
                if (empty($conversation->Recipients[$visitor->user_id])) {
                    return false;
                }

                $recipient = $conversation->Recipients[$visitor->user_id];

                return $recipient->last_read_date < $conversation->last_message_date;
            case self::DYNAMIC_KEY_LAST_MESSAGE:
                return $this->transformer->transformEntity($context, $key, $conversation->LastMessage);
            case self::DYNAMIC_KEY_RECIPIENTS:
                return $this->transformer->transformEntityRelation($context, $key, $conversation, 'Recipients');
        }

        return null;
    }

    public function collectLinks($context)
    {
        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $context->getSource();
        $links = [
            self::LINK_PERMALINK => $this->buildApiLink('conversations', $conversation),
            self::LINK_DETAIL => $this->buildApiLink('conversations', $conversation),
            self::LINK_MESSAGES => $this->buildApiLink(
                'conversation-messages',
                null,
                ['conversation_id' => $conversation->conversation_id]
            )
        ];

        return $links;
    }

    public function collectPermissions($context)
    {
        /** @var \XF\Entity\ConversationMaster $conversation */
        $conversation = $context->getSource();
        $perms = [
            self::PERM_REPLY => $conversation->canReply(),
            self::PERM_DELETE => true,
            self::PERM_UPLOAD_ACTTACHMENT => $conversation->canUploadAndManageAttachments()
        ];

        return $perms;
    }
}