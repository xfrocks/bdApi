<?php

namespace Xfrocks\Api\ControllerPlugin;

use XF\Attachment\Manipulator;
use XF\ControllerPlugin\AbstractPlugin;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\XF\Session\Session;

class Attachment extends AbstractPlugin
{
    public function doUpload(Params $params, $formField, $hash, $contentType, $context)
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $handler = $attachRepo->getAttachmentHandler($contentType);

        $manipulator = new Manipulator($handler, $attachRepo, $context, $hash);
        if (!$manipulator->canUpload($uploadErrors)) {
            throw $this->controller->exception($this->controller->error($uploadErrors));
        }

        $file = $params[$formField];
        if (!$file) {
            throw $this->controller->errorException(\XF::phrase('uploaded_file_failed_not_found'));
        }

        $attachments = [];
        $multiple = false;

        if (is_array($file)) {
            $multiple = true;

            foreach ($file as $fileUploaded) {
                $attachment = $manipulator->insertAttachmentFromUpload($fileUploaded, $error);
                if (!$attachment) {
                    throw $this->controller->errorException($error);
                }

                $attachments[$attachment->attachment_id] = $attachment;
            }
        } else {
            $attachment = $manipulator->insertAttachmentFromUpload($file, $error);
            if (!$attachment) {
                throw $this->controller->errorException($error);
            }

            $attachments = $attachment;
        }

        /** @var AbstractController $controller */
        $controller = $this->controller;

        if ($multiple) {
            $finder = $this->finder('XF:Attachment');
            $finder->whereIds(array_keys($attachments));

            $data = [
                'attachments' => $controller->transformFinderLazily($finder)
            ];
        } else {
            $data = [
                'attachment' => $controller->transformEntityLazily($attachments)
            ];
        }

        return $controller->api($data);
    }

    public function getAttachmentTempHash(Params $params, array $contentData = [])
    {
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