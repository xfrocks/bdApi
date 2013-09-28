<?php

class bdApi_ControllerApi_ConversationMessage extends bdApi_ControllerApi_Abstract
{

	protected function _preDispatch($action)
	{
		$this->_assertRegistrationRequired();
		$this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS);

		return parent::_preDispatch($action);
	}

	public function actionGetIndex()
	{
		$messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
		if (!empty($messageId))
		{
			return $this->responseReroute(__CLASS__, 'get-single');
		}

		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		if (empty($conversationId))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_slash_conversation_messages_requires_conversation_id'), 400);
		}

		$conversation = $this->_getConversationOrError($conversationId);

		$pageNavParams = array('conversation_id' => $conversation['conversation_id']);
		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$limit = XenForo_Application::get('options')->messagesPerPage;

		$inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
		if (!empty($inputLimit))
		{
			$limit = $inputLimit;
			$pageNavParams['limit'] = $inputLimit;
		}

		$fetchOptions = array(
			'limit' => $limit,
			'page' => $page
		);

		$messages = $this->_getConversationModel()->getConversationMessages($conversation['conversation_id'], $this->_getConversationModel()->getFetchOptionsToPrepareApiDataForMessages($fetchOptions));

		$total = $conversation['reply_count'] + 1;

		$data = array(
			'messages' => $this->_filterDataMany($this->_getConversationModel()->prepareApiDataForMessages($messages, $conversation)),
			'messages_total' => $total
		);

		bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'conversation-messages', array(), $pageNavParams);

		return $this->responseData('bdApi_ViewApi_ConversationMessage_List', $data);
	}

	public function actionGetSingle()
	{
		$messageId = $this->_input->filterSingle('message_id', XenForo_Input::UINT);
		$message = $this->_getMessageOrError($messageId);

		$conversation = $this->_getConversationOrError($message['conversation_id']);

		$data = array('message' => $this->_filterDataSingle($this->_getConversationModel()->prepareApiDataForMessage($message, $conversation)), );

		return $this->responseData('bdApi_ViewApi_ConversationMessage_Single', $data);
	}

	public function actionPostIndex()
	{
		$conversationId = $this->_input->filterSingle('conversation_id', XenForo_Input::UINT);
		$conversation = $this->_getConversationOrError($conversationId);

		if (!$this->_getConversationModel()->canReplyToConversation($conversation, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$input = array();
		$input['message_body'] = $this->getHelper('Editor')->getMessageText('message_body', $this->_input);
		$input['message_body'] = XenForo_Helper_String::autoLinkBbCode($input['message_body']);

		$visitor = XenForo_Visitor::getInstance();

		$messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
		$messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_MESSAGE_SENDER, $visitor->toArray());
		$messageDw->set('conversation_id', $conversation['conversation_id']);
		$messageDw->set('user_id', $visitor['user_id']);
		$messageDw->set('username', $visitor['username']);
		$messageDw->set('message', $input['message_body']);

		$messageDw->preSave();

		if (!$messageDw->hasErrors())
		{
			$this->assertNotFlooding('conversation');
		}

		$messageDw->save();
		$message = $messageDw->getMergedData();

		$this->_getConversationModel()->markConversationAsRead($conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time, 0, false);

		$this->_request->setParam('message_id', $message['message_id']);
		return $this->responseReroute(__CLASS__, 'get-single');
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

	protected function _getMessageOrError($messageId, array $fetchOptions = array())
	{
		$message = $this->_getConversationModel()->getConversationMessageById($messageId);

		if (empty($message))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('requested_message_not_found'), 404));
		}

		return $message;
	}

	/**
	 *
	 * @return XenForo_Model_Conversation
	 */
	protected function _getConversationModel()
	{
		return $this->getModelFromCache('XenForo_Model_Conversation');
	}

}
