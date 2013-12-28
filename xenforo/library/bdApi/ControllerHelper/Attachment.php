<?php

class bdApi_ControllerHelper_Attachment extends XenForo_ControllerHelper_Abstract
{
	public function doUpload($formField, $hash, $contentType, array $contentData)
	{
		if (isset($contentData['forum_id']) AND !isset($contentData['node_id']))
		{
			$contentData['node_id'] = $contentData['forum_id'];
		}

		$this->_assertCanUploadAndManageAttachments($hash, $contentType, $contentData);

		$attachmentModel = $this->_getAttachmentModel();
		$attachmentHandler = $attachmentModel->getAttachmentHandler($contentType);
		// known to be valid
		$contentId = $attachmentHandler->getContentIdFromContentData($contentData);

		$existingAttachments = ($contentId ? $attachmentModel->getAttachmentsByContentId($contentType, $contentId) : array());
		$newAttachments = $attachmentModel->getAttachmentsByTempHash($hash);

		$attachmentConstraints = $attachmentHandler->getAttachmentConstraints();

		if ($attachmentConstraints['count'] > 0)
		{
			$remainingUploads = $attachmentConstraints['count'] - (count($existingAttachments) + count($newAttachments));
			if ($remainingUploads <= 0)
			{
				return $this->_controller->responseError(new XenForo_Phrase('you_may_not_upload_more_files_with_message_allowed_x', array('total' => $attachmentConstraints['count'])), 403);
			}
		}

		$file = XenForo_Upload::getUploadedFile($formField);
		if (!$file)
		{
			return $this->_controller->responseError(new XenForo_Phrase('bdapi_requires_upload_x', array('field' => $formField)), 400);
		}

		$file->setConstraints($attachmentConstraints);
		if (!$file->isValid())
		{
			return $this->_controller->responseError($file->getErrors(), 403);
		}
		$dataId = $attachmentModel->insertUploadedAttachmentData($file, XenForo_Visitor::getUserId());
		$attachmentId = $attachmentModel->insertTemporaryAttachment($dataId, $hash);

		return $attachmentModel->getAttachmentById($attachmentId);
	}

	public function doDelete($hash, $attachmentId)
	{
		$attachment = $this->_getAttachmentOrError($attachmentId);
		if (!$this->_getAttachmentModel()->canDeleteAttachment($attachment, $hash))
		{
			return $this->_controller->responseNoPermission();
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$dw->setExistingData($attachment, true);
		$dw->delete();

		return $this->_controller->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	public function doData($attachment)
	{
		if (!$this->_getAttachmentModel()->canViewAttachment($attachment))
		{
			return $this->_controller->responseNoPermission();
		}

		$filePath = $this->_getAttachmentModel()->getAttachmentDataFilePath($attachment);
		if (!file_exists($filePath) || !is_readable($filePath))
		{
			// TODO: add support for alternative attachment storage
			return $this->_controller->responseError(new XenForo_Phrase('attachment_cannot_be_shown_at_this_time'));
		}

		$resize = $this->_controller->getInput()->filter(array(
			'max_width' => XenForo_Input::UINT,
			'max_height' => XenForo_Input::UINT,
			'keep_ratio' => XenForo_Input::UINT,
		));

		$this->_controller->getRouteMatch()->setResponseType('raw');

		$viewParams = array(
			'attachment' => $attachment,
			'attachmentFile' => $filePath,

			'resize' => $resize,
		);

		return $this->_controller->responseData('bdApi_ViewApi_Helper_Attachment_Data', $viewParams);
	}

	public function getAttachmentTempHash($contentData)
	{
		$prefix = '';
		if (!empty($contentData['post_id']))
		{
			$prefix = sprintf('post%d', $contentData['post_id']);
		}
		elseif (!empty($contentData['thread_id']))
		{
			$prefix = sprintf('thread%d', $contentData['thread_id']);
		}
		elseif (!empty($contentData['forum_id']))
		{
			$prefix = sprintf('node%d', $contentData['forum_id']);
		}
		elseif (!empty($contentData['node_id']))
		{
			$prefix = sprintf('node%d', $contentData['node_id']);
		}

		$session = XenForo_Application::getSession();
		$clientId = $session->getOAuthClientId();

		return md5(sprintf('%s%s%s', $prefix, $clientId, XenForo_Application::getConfig()->get('globalSalt')));
	}

	protected function _assertCanUploadAndManageAttachments($hash, $contentType, array $contentData)
	{
		if (!$hash)
		{
			throw $this->_controller->getNoPermissionResponseException();
		}

		$attachmentHandler = $this->_getAttachmentModel()->getAttachmentHandler($contentType);
		if (!$attachmentHandler || !$attachmentHandler->canUploadAndManageAttachments($contentData))
		{
			throw $this->_controller->getNoPermissionResponseException();
		}
	}

	protected function _getAttachmentOrError($attachmentId)
	{
		$attachment = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
		if (!$attachment)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404));
		}

		return $attachment;
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_Attachment');
	}

}
