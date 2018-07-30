<?php

namespace Xfrocks\Api\ControllerPlugin;

use XF\Attachment\Manipulator;
use XF\ControllerPlugin\AbstractPlugin;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\XF\Session\Session;

class Attachment extends AbstractPlugin
{
    public function doDelete($hash, $contentType, $context)
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $handler = $attachRepo->getAttachmentHandler($contentType);

        if (!$handler->canManageAttachments($context, $error)) {
            throw $this->controller->errorException($error);
        }

        /** @var AbstractController $controller */
        $controller = $this->controller;
        $params = $controller->params();

        $manipulator = new Manipulator($handler, $attachRepo, $context, $hash);
        $manipulator->deleteAttachment($params['attachment_id']);

        return $controller->message(\XF::phrase('changes_saved'));
    }

    public function doUpload($hash, $contentType, $context, $formField = 'file')
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $handler = $attachRepo->getAttachmentHandler($contentType);

        if (!$handler->canManageAttachments($context, $error)) {
            throw $this->controller->errorException($error);
        }

        $manipulator = new Manipulator($handler, $attachRepo, $context, $hash);
        if (!$manipulator->canUpload($uploadErrors)) {
            throw $this->controller->exception($this->controller->error($uploadErrors));
        }

        /** @var AbstractController $controller */
        $controller = $this->controller;
        $params = $controller->params();

        $file = $params[$formField];
        if (!$file) {
            throw $this->controller->errorException(\XF::phrase('uploaded_file_failed_not_found'));
        }

        $attachment = $manipulator->insertAttachmentFromUpload($file, $error);
        if (!$attachment) {
            throw $this->controller->errorException($error);
        }

        $data = [
            'attachment' => $controller->transformEntityLazily($attachment)
        ];

        return $controller->api($data);
    }

    public function getAttachmentTempHash(array $contentData = [])
    {
        /** @var AbstractController $controller */
        $controller = $this->controller;
        $params = $controller->params();

        $prefix = '';
        $inputHash = $params['attachment_hash'];

        if (!empty($inputHash)) {
            $prefix = sprintf('hash%s', $inputHash);
        } elseif (!empty($contentData['post_id'])) {
            $prefix = sprintf('post%d', $contentData['post_id']);
        } elseif (!empty($contentData['thread_id'])) {
            $prefix = sprintf('thread%d', $contentData['thread_id']);
        } elseif (!empty($contentData['forum_id'])) {
            $prefix = sprintf('node%d', $contentData['forum_id']);
        } elseif (!empty($contentData['node_id'])) {
            $prefix = sprintf('node%d', $contentData['node_id']);
        } elseif (!empty($contentData['message_id'])) {
            $prefix = sprintf('message%d', $contentData['message_id']);
        } elseif (!empty($contentData['conversation_id'])) {
            $prefix = sprintf('conversation%d', $contentData['conversation_id']);
        }

        /** @var Session $session */
        $session = $this->session();
        /** @var Token|null $token */
        $token = $session->getToken();

        return md5(sprintf(
            'prefix%s_client%s_visitor%d_salt%s',
            $prefix,
            $token ? $token->client_id : '',
            \XF::visitor()->user_id,
            $this->app->config('globalSalt')
        ));
    }
}