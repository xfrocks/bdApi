<?php

class bdApi_XenForo_Model_Conversation extends XFCP_bdApi_XenForo_Model_Conversation
{
    const FETCH_OPTIONS_JOIN = 'bdApi_join';
    const FETCH_OPTIONS_JOIN_FETCH_FIRST_MESSAGE_AVATAR = 0x01;

    public function getFetchOptionsToPrepareApiData(array $fetchOptions = array())
    {
        if (!empty($fetchOptions['join'])) {
            if ($fetchOptions['join'] & XenForo_Model_Conversation::FETCH_FIRST_MESSAGE) {
                if (empty($fetchOptions[self::FETCH_OPTIONS_JOIN])) {
                    $fetchOptions[self::FETCH_OPTIONS_JOIN] = 0;
                }

                $fetchOptions[self::FETCH_OPTIONS_JOIN] |= self::FETCH_OPTIONS_JOIN_FETCH_FIRST_MESSAGE_AVATAR;
            }
        }

        return $fetchOptions;
    }

    public function getFetchOptionsToPrepareApiDataForMessages(array $fetchOptions = array())
    {
        return $fetchOptions;
    }

    public function prepareApiDataForConversations(array $conversations, $getRecipients = false)
    {
        $data = array();

        foreach ($conversations as $key => $conversation) {
            $data[] = $this->prepareApiDataForConversation($conversation, $getRecipients);
        }

        return $data;
    }

    public function prepareApiDataForConversation(array $conversation, $getRecipients = false)
    {
        $conversation = $this->prepareConversation($conversation);

        $publicKeys = array(
            // xf_conversation_master
            'conversation_id' => 'conversation_id',
            'title' => 'conversation_title',
            'user_id' => 'creator_user_id',
            'username' => 'creator_username',
            'start_date' => 'conversation_create_date',
            'last_message_date' => 'conversation_update_date',
        );

        $data = bdApi_Data_Helper_Core::filter($conversation, $publicKeys);

        if (isset($conversation['reply_count'])) {
            $data['conversation_message_count'] = $conversation['reply_count'] + 1;
        }

        $data['conversation_has_new_message'] = !empty($conversation['is_unread']);

        if (isset($conversation['conversation_open']) and isset($conversation['recipient_state'])) {
            switch ($conversation['recipient_state']) {
                case 'active':
                    $data['conversation_is_open'] = empty($conversation['conversation_open']) ? false : true;
                    $data['conversation_is_deleted'] = false;
                    break;
                case 'deleted':
                case 'deleted_ignored':
                    $data['conversation_is_open'] = false;
                    $data['conversation_is_deleted'] = true;
                    break;
            }
        }

        $data['links'] = array(
            'permalink' => XenForo_Link::buildPublicLink('conversations', $conversation),
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('conversations', $conversation),
            'messages' => bdApi_Data_Helper_Core::safeBuildApiLink('conversation-messages', array(), array('conversation_id' => $conversation['conversation_id']))
        );

        $data['permissions'] = array(
            'reply' => $this->canReplyToConversation($conversation),
            'delete' => true,
            'upload_attachment' => $this->canUploadAndManageAttachment($conversation),
        );

        if (isset($conversation['message'])) {
            $firstMessage = $conversation;
            $firstMessage['message_id'] = $conversation['first_message_id'];
            $firstMessage['message_date'] = $conversation['start_date'];

            if (isset($conversation['first_message_avatar_date'])) {
                $firstMessage['avatar_date'] = $conversation['first_message_avatar_date'];
                $firstMessage['gender'] = $conversation['first_message_gender'];
                $firstMessage['gravatar'] = $conversation['first_message_gravatar'];
            }

            $data['first_message'] = $this->prepareApiDataForMessage($firstMessage, $conversation);
        }

        if (!empty($getRecipients)) {
            $recipients = $this->getConversationRecipients($conversation['conversation_id']);
            $data['recipients'] = array();
            foreach ($recipients as $recipient) {
                $data['recipients'][] = array(
                    'user_id' => $recipient['user_id'],
                    'username' => $recipient['username']
                );
            }
        }

        return $data;
    }

    public function prepareApiDataForMessages(array $messages, array $conversation)
    {
        $data = array();

        foreach ($messages as $key => $message) {
            $data[] = $this->prepareApiDataForMessage($message, $conversation);
        }

        return $data;
    }

    public function prepareApiDataForMessage(array $message, array $conversation)
    {
        $message = $this->prepareMessage($message, $conversation);

        $attachments = array();
        if (!empty($message['attachments'])) {
            $attachments = $message['attachments'];
        }

        if (!isset($message['messageHtml'])) {
            $message['messageHtml'] = bdApi_Data_Helper_Message::getHtml($message);
        }
        if (isset($message['message'])) {
            $message['messagePlainText'] = bdApi_Data_Helper_Message::getPlainText($message['message']);
        }

        if (isset($message['signature'])) {
            $message['signaturePlainText'] = bdApi_Data_Helper_Message::getPlainText($message['signature']);
        }

        $publicKeys = array(
            // xf_conversation_message
            'message_id' => 'message_id',
            'conversation_id' => 'conversation_id',
            'user_id' => 'creator_user_id',
            'username' => 'creator_username',
            'message_date' => 'message_create_date',
            'message' => 'message_body',
            'messageHtml' => 'message_body_html',
            'messagePlainText' => 'message_body_plain_text',
            'signature' => 'signature',
            'signatureHtml' => 'signature_html',
            'signaturePlainText' => 'signature_plain_text',
            'attach_count' => 'message_attachment_count',
        );

        $data = bdApi_Data_Helper_Core::filter($message, $publicKeys);

        if (!empty($attachments)) {
            $data['attachments'] = $this->prepareApiDataForAttachments($message, $attachments);
        }

        $data['links'] = array(
            'detail' => bdApi_Data_Helper_Core::safeBuildApiLink('conversation-messages', $message),
            'conversation' => bdApi_Data_Helper_Core::safeBuildApiLink('conversations', $conversation),
            'creator' => bdApi_Data_Helper_Core::safeBuildApiLink('users', $message),
            'creator_avatar' => XenForo_Template_Helper_Core::callHelper('avatar', array(
                $message,
                'm',
                false,
                true
            ))
        );

        $data['permissions'] = array(
            'view' => true,
            'edit' => $this->canEditMessage($message, $conversation),
            'delete' => false,
            'reply' => $this->canReplyToConversation($conversation),
            'upload_attachment' => $this->canUploadAndManageAttachment($conversation) AND $this->canEditMessage($message, $conversation),
        );

        return $data;
    }

    public function prepareApiDataForAttachments(array $message, array $attachments, $tempHash = '')
    {
        $data = array();

        foreach ($attachments as $key => $attachment) {
            $data[] = $this->prepareApiDataForAttachment($message, $attachment, $tempHash);
        }

        return $data;
    }

    public function prepareApiDataForAttachment(array $message, array $attachment, $tempHash = '')
    {
        /* @var $attachmentModel XenForo_Model_Attachment */
        $attachmentModel = $this->getModelFromCache('XenForo_Model_Attachment');
        $attachment = $attachmentModel->prepareAttachment($attachment);

        $publicKeys = array(
            // xf_attachment
            'attachment_id' => 'attachment_id',
            'content_id' => 'message_id',
            'view_count' => 'attachment_download_count',

            // xf_attachment_data
            'filename' => 'filename',
        );

        $data = bdApi_Data_Helper_Core::filter($attachment, $publicKeys);

        $paths = XenForo_Application::get('requestPaths');
        $paths['fullBasePath'] = XenForo_Application::getOptions()->get('boardUrl') . '/';

        $data['links'] = array('permalink' => XenForo_Link::buildPublicLink('attachments', $attachment));

        if (!empty($attachment['thumbnailUrl'])) {
            $data['links']['thumbnail'] = XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true, $paths);
        }

        if (!empty($message['message_id'])) {
            $data['links'] += array(
                'data' => bdApi_Data_Helper_Core::safeBuildApiLink('conversation-messages/attachments', $message, array('attachment_id' => $attachment['attachment_id'])),
                'message' => bdApi_Data_Helper_Core::safeBuildApiLink('conversation-messages', $message),
            );
        }

        $data['permissions'] = array(
            'view' => $attachmentModel->canViewAttachment($attachment, $tempHash),
            'delete' => $attachmentModel->canDeleteAttachment($attachment, $tempHash),
        );

        if (isset($message['messageHtml'])) {
            $data['attachment_is_inserted'] = empty($message['attachments'][$attachment['attachment_id']]);
        }

        return $data;
    }

    public function prepareConversationFetchOptions(array $fetchOptions)
    {
        $prepared = parent::prepareConversationFetchOptions($fetchOptions);
        extract($prepared);

        if (!empty($fetchOptions[self::FETCH_OPTIONS_JOIN])) {
            if ($fetchOptions[self::FETCH_OPTIONS_JOIN] & self::FETCH_OPTIONS_JOIN_FETCH_FIRST_MESSAGE_AVATAR) {
                $selectFields .= ',
						first_message_user.avatar_date AS first_message_avatar_date,
						first_message_user.gender AS first_message_gender,
						first_message_user.gravatar AS first_message_gravatar';
                $joinTables .= '
						LEFT JOIN xf_user AS first_message_user ON
						(first_message_user.user_id = conversation_master.user_id)';
            }
        }

        return compact(array_keys($prepared));
    }

}
