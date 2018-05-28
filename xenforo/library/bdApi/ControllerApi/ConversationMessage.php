<?php

class bdApi_ControllerApi_ConversationMessage extends bdApi_ControllerApi_Abstract
{

    protected function _preDispatch($action)
    {
        $this->_assertRegistrationRequired();
        $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS);

        parent::_preDispatch($action);
    }

    public function actionGetIndex()
    {
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
        if (!empty($messageId)) {
            return $this->responseReroute(__CLASS__, 'single');
        }

        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        if (empty($conversationId)) {
            return $this->responseError(
                new XenForo_Phrase('bdapi_slash_conversation_messages_requires_conversation_id'),
                400
            );
        }

        $beforeDate = $this->_input->filterSingle('before', XenForo_Input::DATE_TIME);
        $afterDate = $this->_input->filterSingle('after', XenForo_Input::DATE_TIME, array(
            'dayEnd' => true
        ));

        if ($beforeDate && $afterDate && $afterDate < $beforeDate) {
            return $this->responseError(
                new XenForo_Phrase('bdapi_slash_conversation_messages_requires_param_after_great_than_before'),
                400
            );
        }

        $conversation = $this->_getConversationOrError($conversationId);

        $pageNavParams = array('conversation_id' => $conversation['conversation_id']);
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page,
            bdApi_Extend_Model_Conversation::FETCH_OPTIONS_MESSAGES_BEFORE_DATE => $beforeDate,
            bdApi_Extend_Model_Conversation::FETCH_OPTIONS_MESSAGES_AFTER_DATE => $afterDate
        );

        $order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'natural'));
        switch ($order) {
            case 'natural_reverse':
                // load the class to make our constant accessible
                $this->_getConversationModel();
                $fetchOptions[bdApi_Extend_Model_Conversation::FETCH_OPTIONS_MESSAGES_ORDER_REVERSE] = true;
                $pageNavParams['order'] = $order;
                break;
        }

        $messages = $this->_getConversationModel()->getConversationMessages(
            $conversation['conversation_id'],
            $this->_getConversationModel()->getFetchOptionsToPrepareApiDataForMessages($fetchOptions)
        );
        if (!$this->_isFieldExcluded('attachments')) {
            $messages = $this->_getConversationModel()->getAndMergeAttachmentsIntoConversationMessages($messages);
        }

        $total = $conversation['reply_count'] + 1;

        $data = array(
            'messages' => $this->_filterDataMany($this->_getConversationModel()->prepareApiDataForMessages(
                $messages,
                $conversation
            )),
            'messages_total' => $total
        );

        if (!$this->_isFieldExcluded('conversation')) {
            $data['conversation'] = $this->_filterDataSingle(
                $this->_getConversationModel()->prepareApiDataForConversation($conversation),
                array('conversation')
            );
        }

        bdApi_Data_Helper_Core::addPageLinks(
            $this->getInput(),
            $data,
            $limit,
            $total,
            $page,
            'conversation-messages',
            array(),
            $pageNavParams
        );

        return $this->responseData('bdApi_ViewApi_ConversationMessage_List', $data);
    }

    public function actionSingle()
    {
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
        $message = $this->_getMessageOrError($messageId);

        $conversation = $this->_getConversationOrError($message['conversation_id']);

        if (!$this->_isFieldExcluded('attachments')) {
            $messages = array($message['message_id'] => $message);
            $messages = $this->_getConversationModel()->getAndMergeAttachmentsIntoConversationMessages($messages);
            $message = reset($messages);
        }

        $data = array(
            'message' => $this->_filterDataSingle($this->_getConversationModel()->prepareApiDataForMessage(
                $message,
                $conversation
            ))
        );

        return $this->responseData('bdApi_ViewApi_ConversationMessage_Single', $data);
    }

    public function actionPostIndex()
    {
        $input = $this->_input->filter(array(
            'conversation_id' => XenForo_Input::UINT,
        ));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['message_body'] = $editorHelper->getMessageText('message_body', $this->_input);
        $input['message_body'] = XenForo_Helper_String::autoLinkBbCode($input['message_body']);

        $conversation = $this->_getConversationOrError($input['conversation_id']);

        if (!$this->_getConversationModel()->canReplyToConversation($conversation, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $visitor = XenForo_Visitor::getInstance();

        $messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
        $messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_MESSAGE_SENDER, $visitor->toArray());
        $messageDw->set('conversation_id', $conversation['conversation_id']);
        $messageDw->set('user_id', $visitor['user_id']);
        $messageDw->set('username', $visitor['username']);
        $messageDw->set('message', $input['message_body']);
        $messageDw->setExtraData(
            XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH,
            $this->_getAttachmentHelper()->getAttachmentTempHash($conversation)
        );

        switch ($this->_spamCheck(array(
            'content_type' => 'conversation_message',
            'content' => $input['message_body'],
        ))) {
            case self::SPAM_RESULT_MODERATED:
            case self::SPAM_RESULT_DENIED:
                return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                break;
        }

        $messageDw->preSave();

        if ($messageDw->hasErrors()) {
            return $this->responseErrors($messageDw->getErrors(), 400);
        }

        $this->assertNotFlooding('conversation');

        $messageDw->save();
        $message = $messageDw->getMergedData();

        $this->_getConversationModel()->markConversationAsRead(
            $conversation['conversation_id'],
            XenForo_Visitor::getUserId(),
            XenForo_Application::$time,
            0,
            false
        );

        $this->_request->setParam('message_id', $message['message_id']);
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionPutIndex()
    {
        $input = $this->_input->filter(array(
            'message_id' => XenForo_Input::UINT,
        ));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['message_body'] = $editorHelper->getMessageText('message_body', $this->_input);
        $input['message_body'] = XenForo_Helper_String::autoLinkBbCode($input['message_body']);

        $message = $this->_getMessageOrError($input['message_id']);
        $conversation = $this->_getConversationOrError($message['conversation_id']);

        if (!$this->_getConversationModel()->canEditMessage($message, $conversation, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
        $messageDw->setExistingData($message, true);
        $messageDw->set('message', $input['message_body']);
        $messageDw->setExtraData(
            XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH,
            $this->_getAttachmentHelper()->getAttachmentTempHash($message)
        );

        switch ($this->_spamCheck(array(
            'content_type' => 'conversation_message',
            'content_id' => $message['message_id'],
            'content' => $input['message_body'],
        ))) {
            case self::SPAM_RESULT_MODERATED:
            case self::SPAM_RESULT_DENIED:
                return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                break;
        }

        $messageDw->preSave();

        if ($messageDw->hasErrors()) {
            return $this->responseErrors($messageDw->getErrors(), 400);
        }

        $messageDw->save();

        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionDeleteIndex()
    {
        // XenForo does not support message deletion
        return $this->responseNoPermission();
    }

    public function actionGetAttachments()
    {
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);

        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
        if (!empty($attachmentId)) {
            return $this->responseReroute('bdApi_ControllerApi_Attachment', 'get-data');
        }

        $message = $this->_getMessageOrError($messageId);
        $conversation = $this->_getConversationOrError($message['conversation_id']);

        $messages = array($message['message_id'] => $message);
        $messages = $this->_getConversationModel()->getAndMergeAttachmentsIntoConversationMessages($messages);
        $messages = $this->_getConversationModel()->prepareApiDataForMessages($messages, $conversation);

        $message = reset($messages);
        $attachments = isset($message['attachments']) ? $message['attachments'] : array();

        $data = array('attachments' => $this->_filterDataMany($attachments));

        return $this->responseData('bdApi_ViewApi_ConversationMessage_Attachments', $data);
    }

    public function actionPostAttachments()
    {
        $contentData = $this->_input->filter(array(
            'conversation_id' => XenForo_Input::UINT,
            'message_id' => XenForo_Input::UINT,
        ));
        if (empty($contentData['conversation_id']) AND empty($contentData['message_id'])) {
            return $this->responseError(
                new XenForo_Phrase('bdapi_slash_conversation_messages_attachments_requires_ids'),
                400
            );
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        $response = $attachmentHelper->doUpload('file', $hash, 'conversation_message', $contentData);

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

        return $this->responseData('bdApi_ViewApi_ConversationMessage_Attachments', $data);
    }

    public function actionDeleteAttachments()
    {
        $contentData = $this->_input->filter(array(
            'conversation_id' => XenForo_Input::UINT,
            'message_id' => XenForo_Input::UINT,
        ));
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

        if (empty($contentData['conversation_id']) AND empty($contentData['message_id'])) {
            return $this->responseError(
                new XenForo_Phrase('bdapi_slash_conversation_messages_attachments_requires_ids'),
                400
            );
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        return $attachmentHelper->doDelete($hash, $attachmentId);
    }

    public function actionPostReport()
    {
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
        $reportMessage = $this->_input->filterSingle('message', XenForo_Input::STRING);

        $message = $this->_getMessageOrError($messageId);
        $conversation = $this->_getConversationOrError($message['conversation_id']);

        if (!$this->_getConversationModel()->canReportMessage($message, $conversation, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        if (!$reportMessage) {
            return $this->responseError(new XenForo_Phrase(
                'bdapi_slash_x_report_requires_message',
                array('route' => 'conversation-messages')
            ), 400);
        }

        $this->assertNotFlooding('report');

        $message['conversation'] = $conversation;

        /* @var $reportModel XenForo_Model_Report */
        $reportModel = $this->getModelFromCache('XenForo_Model_Report');
        $reportModel->reportContent('conversation_message', $message, $reportMessage);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
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

    protected function _getMessageOrError($messageId, array $fetchOptions = array())
    {
        $message = $this->_getConversationModel()->getConversationMessageById($messageId, $fetchOptions);

        if (empty($message)) {
            throw $this->responseException($this->responseError(
                new XenForo_Phrase('requested_message_not_found'),
                404
            ));
        }

        return $message;
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

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'XenForo_ControllerPublic_Conversation';
    }
}
