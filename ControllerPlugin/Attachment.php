<?php

namespace Xfrocks\Api\ControllerPlugin;

use XF\Attachment\Manipulator;
use XF\ControllerPlugin\AbstractPlugin;
use XF\PrintableException;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Api\XF\ApiOnly\Session\Session;

class Attachment extends AbstractPlugin
{
    public function doUpload($hash, $contentType, $context, $formField = 'file')
    {
        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');
        $handler = $attachRepo->getAttachmentHandler($contentType);

        if (!$handler) {
            throw new PrintableException('Invalid content type.');
        }

        if (!$handler->canManageAttachments($context, $error)) {
            throw $this->controller->exception($this->controller->noPermission($error));
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
            throw $this->controller->exception($this->controller->noPermission($error));
        }
        return $attachment;
    }

    public function doUploadAndRespond($hash, $contentType, $context, $formField = 'file')
    {
        $attachment = $this->doUpload($hash, $contentType, $context, $formField);

        /** @var AbstractController $controller */
        $controller = $this->controller;
        $lazyTransformer = $controller->transformEntityLazily($attachment);
        $lazyTransformer->addCallbackPreTransform(function ($context) use ($hash) {
            /** @var TransformContext $context */
            $context->setData($hash, 'tempHash');

            return $context;
        });

        return $controller->api(['attachment' => $lazyTransformer]);
    }

    public function getAttachmentTempHash(array $contentData = [])
    {
        /** @var AbstractController $controller */
        $controller = $this->controller;
        $params = $controller->params();

        $prefix = '';

        if (!empty($params['attachment_hash'])) {
            $prefix = sprintf('hash%s', $params['attachment_hash']);
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
        } elseif (!empty($contentData['media_album_id'])) {
            $prefix = sprintf('media_album%d', $contentData['media_album_id']);
        } elseif (!empty($contentData['media_category_id'])) {
            $prefix = sprintf('media_category%d', $contentData['media_category_id']);
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
