<?php

class bdApi_ControllerApi_Attachment extends bdApi_ControllerApi_Abstract
{
    public function actionGetData()
    {
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
        $attachment = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
        if (empty($attachment)) {
            return $this->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404);
        }

        return $this->_getAttachmentHelper()->doData($attachment);
    }

    /**
     * @return XenForo_Model_Attachment
     */
    protected function _getAttachmentModel()
    {
        return $this->getModelFromCache('XenForo_Model_Attachment');
    }

    /**
     * @return bdApi_ControllerHelper_Attachment
     */
    protected function _getAttachmentHelper()
    {
        return $this->getHelper('bdApi_ControllerHelper_Attachment');
    }
}