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
            return $this->responseError(new XenForo_Phrase('bdapi_slash_conversation_messages_requires_conversation_id'), 400);
        }

        $conversation = $this->_getConversationOrError($conversationId);

        $pageNavParams = array('conversation_id' => $conversation['conversation_id']);
        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $limit = XenForo_Application::get('options')->messagesPerPage;

        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page
        );

        $messages = $this->_getConversationModel()->getConversationMessages($conversation['conversation_id'], $this->_getConversationModel()->getFetchOptionsToPrepareApiDataForMessages($fetchOptions));
        if (!$this->_isFieldExcluded('attachments')) {
            $messages = $this->_getConversationModel()->getAndMergeAttachmentsIntoConversationMessages($messages);
        }

        $total = $conversation['reply_count'] + 1;

        $data = array(
            'messages' => $this->_filterDataMany($this->_getConversationModel()->prepareApiDataForMessages($messages, $conversation)),
            'messages_total' => $total
        );

        if (!$this->_isFieldExcluded('conversation')) {
            $data['conversation'] = $this->_filterDataSingle($this->_getConversationModel()->prepareApiDataForConversation($conversation), array('conversation'));
        }

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'conversation-messages', array(), $pageNavParams);

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

        $data = array('message' => $this->_filterDataSingle($this->_getConversationModel()->prepareApiDataForMessage($message, $conversation)));

        return $this->responseData('bdApi_ViewApi_ConversationMessage_Single', $data);
    }

    public function actionPostIndex()
    {
        $conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
        $conversation = $this->_getConversationOrError($conversationId);

        if (!$this->_getConversationModel()->canReplyToConversation($conversation, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        // TODO
        $input = $this->_input->filter(array());

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['message_body'] = $editorHelper->getMessageText('message_body', $this->_input);
        $input['message_body'] = XenForo_Helper_String::autoLinkBbCode($input['message_body']);

        $visitor = XenForo_Visitor::getInstance();

        $messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
        $messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_MESSAGE_SENDER, $visitor->toArray());
        $messageDw->set('conversation_id', $conversation['conversation_id']);
        $messageDw->set('user_id', $visitor['user_id']);
        $messageDw->set('username', $visitor['username']);
        $messageDw->set('message', $input['message_body']);
        $messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($conversation));

        $messageDw->preSave();

        if ($messageDw->hasErrors()) {
            return $this->responseErrors($messageDw->getErrors(), 400);
        }

        $this->assertNotFlooding('conversation');

        $messageDw->save();
        $message = $messageDw->getMergedData();

        $this->_getConversationModel()->markConversationAsRead($conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time, 0, false);

        $this->_request->setParam('message_id', $message['message_id']);
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionPutIndex()
    {
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);

        $message = $this->_getMessageOrError($messageId);
        $conversation = $this->_getConversationOrError($message['conversation_id']);

        if (!$this->_getConversationModel()->canEditMessage($message, $conversation, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        // TODO
        $input = $this->_input->filter(array());

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['message_body'] = $editorHelper->getMessageText('message_body', $this->_input);
        $input['message_body'] = XenForo_Helper_String::autoLinkBbCode($input['message_body']);

        $messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
        $messageDw->setExistingData($message, true);
        $messageDw->set('message', $input['message_body']);
        $messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($message));
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

        $message = $this->_getMessageOrError($messageId);
        $conversation = $this->_getConversationOrError($message['conversation_id']);

        $messages = array($message['message_id'] => $message);
        $messages = $this->_getConversationModel()->getAndMergeAttachmentsIntoConversationMessages($messages);
        $message = reset($messages);

        if (empty($attachmentId)) {
            $message = $this->_getConversationModel()->prepareApiDataForMessage($message, $conversation);
            $attachments = isset($message['attachments']) ? $message['attachments'] : array();

            $data = array('attachments' => $this->_filterDataMany($attachments));
        } else {
            $attachments = isset($message['attachments']) ? $message['attachments'] : array();
            $attachment = false;

            foreach ($attachments as $_attachment) {
                if ($_attachment['attachment_id'] == $attachmentId) {
                    $attachment = $_attachment;
                }
            }

            if (!empty($attachment)) {
                return $this->_getAttachmentHelper()->doData($attachment);
            } else {
                return $this->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404);
            }
        }

        return $this->responseData('bdApi_ViewApi_ConversationMessage_Attachments', $data);
    }

    public function actionPostAttachments()
    {
        $contentData = $this->_input->filter(array(
            'conversation_id' => XenForo_Input::UINT,
            'message_id' => XenForo_Input::UINT,
        ));
        if (empty($contentData['conversation_id']) AND empty($contentData['message_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_conversation_messages_attachments_requires_ids'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        $response = $attachmentHelper->doUpload('file', $hash, 'conversation_message', $contentData);

        if ($response instanceof XenForo_ControllerResponse_Abstract) {
            return $response;
        }

        $data = array('attachment' => $this->_filterDataSingle($this->_getConversationModel()->prepareApiDataForAttachment($contentData, $response, $hash)));

        return $this->responseData('bdApi_ViewApi_ConversationMessage_Attachments', $data);
    }

    public function actionDeleteAttachments()
    {
        $contentData = $this->_input->filter(array(
            'conversation_id' => XenForo_Input::UINT,
            'message_id' => XenForo_Input::UINT,
        ));
        if (empty($contentData['conversation_id']) AND empty($contentData['message_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_conversation_messages_attachments_requires_ids'), 400);
        }

        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        return $attachmentHelper->doDelete($hash, $attachmentId);
    }

    public function actionPostReport()
    {
        $messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);

        $message = $this->_getMessageOrError($messageId);
        $conversation = $this->_getConversationOrError($message['conversation_id']);

        if (!$this->_getConversationModel()->canReportMessage($message, $conversation, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $reportMessage = $this->_input->filterSingle('message', XenForo_Input::STRING);
        if (!$reportMessage) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_x_report_requires_message', array('route' => 'conversation-messages')), 400);
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
        $conversation = $this->_getConversationModel()->getConversationForUser($conversationId, XenForo_Visitor::getUserId(), $this->_getConversationModel()->getFetchOptionsToPrepareApiData($fetchOptions));

        if (empty($conversation)) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('requested_conversation_not_found'), 404));
        }

        return $conversation;
    }

    protected function _getMessageOrError($messageId, array $fetchOptions = array())
    {
        $message = $this->_getConversationModel()->getConversationMessageById($messageId, $fetchOptions);

        if (empty($message)) {
            throw $this->responseException($this->responseError(new XenForo_Phrase('requested_message_not_found'), 404));
        }

        return $message;
    }

    /**
     *
     * @return bdApi_XenForo_Model_Conversation
     */
    protected function _getConversationModel()
    {
        return $this->getModelFromCache('XenForo_Model_Conversation');
    }

    /**
     *
     * @return bdApi_ControllerHelper_Attachment
     */
    protected function _getAttachmentHelper()
    {
        return $this->getHelper('bdApi_ControllerHelper_Attachment');
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'XenForo_ControllerPublic_Conversation';
    }
}
