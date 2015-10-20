<?php

class bdApi_XenForo_Model_Conversation extends XFCP_bdApi_XenForo_Model_Conversation
{
    const FETCH_OPTIONS_JOIN = 'bdApi_join';
    const FETCH_OPTIONS_JOIN_FETCH_FIRST_MESSAGE_AVATAR = 0x01;

    public function insertConversationAlert(
        array $conversation,
        array $alertUser,
        $action,
        array $triggerUser = null,
        array $extraData = null,
        array &$messageInfo = null)
    {
        parent::insertConversationAlert($conversation, $alertUser, $action,
            $triggerUser, $extraData, $messageInfo);

        if (!bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_NOTIFICATION)
            || !bdApi_Option::get('userNotificationConversation')
        ) {
            return;
        }

        if (!$triggerUser) {
            $triggerUser = array(
                'user_id' => $conversation['last_message_user_id'],
                'username' => $conversation['last_message_username']
            );
        }

        if ($triggerUser['user_id'] == $alertUser['user_id']) {
            return;
        }

        if (empty($extraData)) {
            $extraData = array();
        }
        $extraData['object_data'] = array(
            'notification_id' => 0,
            'notification_html' => '',
        );
        if (!isset($messageInfo['messageText'])) {
            $bbCodeParserText = XenForo_BbCode_Parser::create(XenForo_BbCode_Formatter_Base::create('Text'));
            $messageInfo['messageText'] = strval(new XenForo_BbCode_TextWrapper($messageInfo['message'], $bbCodeParserText));
        }
        $extraData['object_data']['message'] = array(
            'message_id' => $extraData['message_id'],
            'conversation_id' => $conversation['conversation_id'],
            'message' => strval($messageInfo['messageText']),
        );

        $fakeAlert = array(
            'alert_id' => 0,
            'alerted_user_id' => $alertUser['user_id'],
            'user_id' => $triggerUser['user_id'],
            'username' => $triggerUser['username'],
            'content_type' => 'conversation',
            'content_id' => $conversation['conversation_id'],
            'action' => $action,
            'event_date' => XenForo_Application::$time,
            'view_date' => 0,
            'extra_data' => serialize($extraData),
        );

        if ($fakeAlert['alerted_user_id'] > 0) {
            /* @var $alertModel bdApi_XenForo_Model_Alert */
            $alertModel = $this->getModelFromCache('XenForo_Model_Alert');
            $option = $alertModel->bdApi_getUserNotificationOption($fakeAlert['alerted_user_id']);
        }

        if (!empty($option)) {
            if ($fakeAlert['user_id'] == XenForo_Visitor::getUserId()) {
                $fakeAlert = array_merge($fakeAlert, XenForo_Visitor::getInstance()->toArray());
            } else {
                /** @var XenForo_Model_User $userModel */
                $userModel = $this->getModelFromCache('XenForo_Model_User');
                $user = $userModel->getUserById($fakeAlert['user_id']);
                $fakeAlert = array_merge($fakeAlert, $user);
            }

            /* @var $subscriptionModel bdApi_Model_Subscription */
            $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
            $subscriptionModel->ping($option, 'insert', bdApi_Model_Subscription::TYPE_NOTIFICATION, $fakeAlert);
        }

        return;
    }

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

        $data['user_is_ignored'] = XenForo_Visitor::getInstance()->isIgnoring($conversation['user_id']);

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

        $data['user_is_ignored'] = XenForo_Visitor::getInstance()->isIgnoring($message['user_id']);

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
            )),
            'report' => bdApi_Data_Helper_Core::safeBuildApiLink('conversation-messages/report', $message),
        );

        $data['permissions'] = array(
            'view' => true,
            'edit' => $this->canEditMessage($message, $conversation),
            'delete' => false,
            'reply' => $this->canReplyToConversation($conversation),
            'upload_attachment' => $this->canUploadAndManageAttachment($conversation) AND $this->canEditMessage($message, $conversation),
            'report' => $this->canReportMessage($message, $conversation),
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
