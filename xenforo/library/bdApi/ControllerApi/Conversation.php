<?php

class bdApi_ControllerApi_Conversation extends bdApi_ControllerApi_Abstract
{

    protected function _preDispatch($action)
    {
        $this->_assertRegistrationRequired();
        $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS);

        parent::_preDispatch($action);
    }

    public function actionGetIndex()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        if (!empty($conversationId)) {
            return $this->responseReroute(__CLASS__, 'single');
        }

        $visitor = XenForo_Visitor::getInstance();

        $pageNavParams = array();
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $conditions = array();
        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page,
            'join' => 0
        );

        // TODO: ordering

        $conversations = $this->_getConversationModel()->getConversationsForUser(
            $visitor['user_id'],
            $conditions,
            $this->_getConversationModel()->getFetchOptionsToPrepareApiData($fetchOptions)
        );
        $conversationsData = $this->_prepareConversations($conversations);

        $total = $this->_getConversationModel()->countConversationsForUser($visitor['user_id'], $conditions);

        $data = array(
            'conversations' => $this->_filterDataMany($conversationsData),
            'conversations_total' => $total
        );

        bdApi_Data_Helper_Core::addPageLinks(
            $this->getInput(),
            $data,
            $limit,
            $total,
            $page,
            'conversations',
            array(),
            $pageNavParams
        );

        return $this->responseData('bdApi_ViewApi_Conversation_List', $data);
    }

    public function actionSingle()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        $conversation = $this->_getConversationOrError($conversationId);

        $conversations = array($conversationId => $conversation);
        $conversationsData = $this->_prepareConversations($conversations);

        $conversationData = reset($conversationsData);
        if (empty($conversationData)) {
            return $this->responseNoPermission();
        }

        $data = array(
            'conversation' => $this->_filterDataSingle($conversationData)
        );

        return $this->responseData('bdApi_ViewApi_Conversation_Single', $data);
    }

    public function actionPostIndex()
    {
        $input = $this->_input->filter(array(
            'recipients' => XenForo_Input::STRING,
            'conversation_title' => XenForo_Input::STRING
        ));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['message_body'] = $editorHelper->getMessageText('message_body', $this->_input);
        $input['message_body'] = XenForo_Helper_String::autoLinkBbCode($input['message_body']);

        if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $visitor = XenForo_Visitor::getInstance();

        /* @var $conversationDw XenForo_DataWriter_ConversationMaster */
        $conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
        $conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $visitor->toArray());
        $conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $input['message_body']);
        $conversationDw->set('user_id', $visitor['user_id']);
        $conversationDw->set('username', $visitor['username']);
        $conversationDw->set('title', $input['conversation_title']);
        $conversationDw->addRecipientUserNames(explode(',', $input['recipients']));

        $messageDw = $conversationDw->getFirstMessageDw();
        $messageDw->set('message', $input['message_body']);
        $messageDw->setExtraData(
            XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH,
            $this->_getAttachmentHelper()->getAttachmentTempHash()
        );

        switch ($this->_spamCheck(array(
            'content_type' => 'conversation',
            'content' => $input['conversation_title'] . "\n" . $input['message_body'],
        ))) {
            case self::SPAM_RESULT_MODERATED:
            case self::SPAM_RESULT_DENIED:
                return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                break;
        }

        $conversationDw->preSave();

        if (!$conversationDw->hasErrors()) {
            $this->assertNotFlooding('conversation');
        }

        $conversationDw->save();
        $conversation = $conversationDw->getMergedData();

        $this->_getConversationModel()->markConversationAsRead(
            $conversation['conversation_id'],
            XenForo_Visitor::getUserId(),
            XenForo_Application::$time
        );

        $this->_request->setParam('conversation_id', $conversation['conversation_id']);
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionDeleteIndex()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);

        $this->_getConversationModel()->deleteConversationForUser(
            $conversationId,
            XenForo_Visitor::getUserId(),
            'deleted'
        );

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostAttachments()
    {
        $contentData = array('message_id' => 0);
        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash();
        $response = $attachmentHelper->doUpload('file', $hash, 'conversation_message');

        if ($response instanceof XenForo_ControllerResponse_Abstract) {
            return $response;
        }

        $data = array(
            'attachment' => $this->_filterDataSingle(
                $this->_getConversationModel()->prepareApiDataForAttachment(
                    $response,
                    $contentData,
                    $contentData,
                    $hash
                )
            )
        );

        return $this->responseData('bdApi_ViewApi_Conversation_Attachments', $data);
    }

    public function actionDeleteAttachments()
    {
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash();
        return $attachmentHelper->doDelete($hash, $attachmentId);
    }

    protected function _getConversationOrError($conversationId, array $fetchOptions = array())
    {
        $conversation = $this->_getConversationModel()->getConversationForUser(
            $conversationId,
            XenForo_Visitor::getUserId(),
            $this->_getConversationModel()->getFetchOptionsToPrepareApiData($fetchOptions)
        );

        if (empty($conversation)) {
            throw $this->responseException($this->responseError(
                new XenForo_Phrase('requested_conversation_not_found'),
                404
            ));
        }

        return $conversation;
    }

    /**
     *
     * @return bdApi_Extend_Model_Conversation
     */
    protected function _getConversationModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('XenForo_Model_Conversation');
    }

    /**
     *
     * @return bdApi_ControllerHelper_Attachment
     */
    protected function _getAttachmentHelper()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getHelper('bdApi_ControllerHelper_Attachment');
    }

    protected function _prepareConversations(array $conversations)
    {
        $getRecipients = !$this->_isFieldExcluded('recipients');
        $getFirstMessage = !$this->_isFieldExcluded('first_message');
        $getLastMessage = $this->_isFieldIncluded('last_message');

        $firstMessageIds = array();
        $lastMessageIds = array();
        $messages = array();

        foreach ($conversations as $conversationId => $conversationRef) {
            if ($getFirstMessage) {
                $firstMessageIds[$conversationId] = $conversationRef['first_message_id'];
            }
            if ($getLastMessage) {
                $lastMessageIds[$conversationId] = $conversationRef['last_message_id'];
            }
        }

        if (!empty($firstMessageIds)
            || !empty($lastMessageIds)
        ) {
            $messages = $this->_getConversationModel()->bdApi_getConversationMessagesByIds(
                array_merge(
                    array_values($lastMessageIds),
                    array_values($firstMessageIds)
                ),
                $this->_getConversationModel()->getFetchOptionsToPrepareApiDataForMessages()
            );
        }

        $conversationsData = [];
        foreach ($conversations as &$conversationRef) {
            $conversationData = $this->_getConversationModel()->prepareApiDataForConversation(
                $conversationRef,
                $getRecipients
            );
            if ($getFirstMessage) {
                if (!empty($firstMessageIds)
                    && isset($messages[$conversationRef['first_message_id']])
                ) {
                    $conversationData['first_message'] = $this->_getConversationModel()->prepareApiDataForMessage(
                        $messages[$conversationRef['first_message_id']],
                        $conversationRef
                    );
                }
            }
            if ($getLastMessage) {
                if (!empty($lastMessageIds)
                    && isset($messages[$conversationRef['last_message_id']])
                ) {
                    $conversationData['last_message'] = $this->_getConversationModel()->prepareApiDataForMessage(
                        $messages[$conversationRef['last_message_id']],
                        $conversationRef
                    );
                }
            }
            $conversationsData[] = $conversationData;
        }

        return $conversationsData;
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'XenForo_ControllerPublic_Conversation';
    }
}
