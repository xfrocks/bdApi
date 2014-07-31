<?php

class bdApi_ControllerApi_Conversation extends bdApi_ControllerApi_Abstract
{

	protected function _preDispatch($action)
	{
		$this->_assertRegistrationRequired();
		$this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS);

		return parent::_preDispatch($action);
	}

	public function actionGetIndex()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		if (!empty($conversationId))
		{
			return $this->responseReroute(__CLASS__, 'get-single');
		}

		$visitor = XenForo_Visitor::getInstance();

		$pageNavParams = array();
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$limit = XenForo_Application::get('options')->discussionsPerPage;

		$inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
		if (!empty($inputLimit))
		{
			$limit = $inputLimit;
			$pageNavParams['limit'] = $inputLimit;
		}

		$conditions = array();
		$fetchOptions = array(
			'limit' => $limit,
			'page' => $page,
			'join' => 0
		);

		// TODO: ordering

		if (!$this->_isFieldExcluded('first_message'))
		{
			$fetchOptions['join'] += XenForo_Model_Conversation::FETCH_FIRST_MESSAGE;
		}

		$getRecipients = !$this->_isFieldExcluded('recipients');

		$conversations = $this->_getConversationModel()->getConversationsForUser($visitor['user_id'], $conditions, $this->_getConversationModel()->getFetchOptionsToPrepareApiData($fetchOptions));

		$total = $this->_getConversationModel()->countConversationsForUser($visitor['user_id'], $conditions);

		$data = array(
			'conversations' => $this->_filterDataMany($this->_getConversationModel()->prepareApiDataForConversations($conversations, $getRecipients)),
			'conversations_total' => $total
		);

		bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'conversations', array(), $pageNavParams);

		return $this->responseData('bdApi_ViewApi_Conversation_List', $data);
	}

	public function actionGetSingle()
	{
		$visitor = XenForo_Visitor::getInstance();

		$fetchOptions = array('join' => 0);

		if (!$this->_isFieldExcluded('first_message'))
		{
			$fetchOptions['join'] += XenForo_Model_Conversation::FETCH_FIRST_MESSAGE;
		}

		$getRecipients = !$this->_isFieldExcluded('recipients');

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId, $fetchOptions);

		$data = array('conversation' => $this->_filterDataSingle($this->_getConversationModel()->prepareApiDataForConversation($conversation, $getRecipients)));

		return $this->responseData('bdApi_ViewApi_Thread_Single', $data);
	}

	public function actionPostIndex()
	{
		if (!$this->_getConversationModel()->canStartConversations($errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$input = $this->_input->filter(array(
			'recipients' => XenForo_Input::STRING,
			'conversation_title' => XenForo_Input::STRING
		));
		$input['message_body'] = $this->getHelper('Editor')->getMessageText('message_body', $this->_input);
		$input['message_body'] = XenForo_Helper_String::autoLinkBbCode($input['message_body']);

		$visitor = XenForo_Visitor::getInstance();

		$conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
		$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $visitor->toArray());
		$conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $input['message_body']);
		$conversationDw->set('user_id', $visitor['user_id']);
		$conversationDw->set('username', $visitor['username']);
		$conversationDw->set('title', $input['conversation_title']);
		$conversationDw->addRecipientUserNames(explode(',', $input['recipients']));
		// checks permissions

		$messageDw = $conversationDw->getFirstMessageDw();
		$messageDw->set('message', $input['message_body']);
		$messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash());

		$conversationDw->preSave();

		if (!$conversationDw->hasErrors())
		{
			$this->assertNotFlooding('conversation');
		}

		$conversationDw->save();
		$conversation = $conversationDw->getMergedData();

		$this->_getConversationModel()->markConversationAsRead($conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time);

		$this->_request->setParam('conversation_id', $conversation['conversation_id']);
		return $this->responseReroute(__CLASS__, 'get-single');
	}

	public function actionDeleteIndex()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);

		$this->_getConversationModel()->deleteConversationForUser($conversationId, XenForo_Visitor::getUserId(), 'deleted');

		return $this->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	public function actionPostAttachments()
	{
		$attachmentHelper = $this->_getAttachmentHelper();
		$hash = $attachmentHelper->getAttachmentTempHash();
		$response = $attachmentHelper->doUpload('file', $hash, 'conversation_message');

		if ($response instanceof XenForo_ControllerResponse_Abstract)
		{
			return $response;
		}

		$data = array('attachment' => $this->_getConversationModel()->prepareApiDataForAttachment(array('message_id' => 0), $response, $hash));

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
		$conversation = $this->_getConversationModel()->getConversationForUser($conversationId, XenForo_Visitor::getUserId(), $this->_getConversationModel()->getFetchOptionsToPrepareApiData($fetchOptions));

		if (empty($conversation))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_conversation_not_found'), 404));
		}

		return $conversation;
	}

	/**
	 *
	 * @return XenForo_Model_Conversation
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

}
