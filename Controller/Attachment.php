<?php

namespace Xfrocks\Api\Controller;

use XF\Attachment\Manipulator;
use XF\Mvc\ParameterBag;

class Attachment extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->attachment_id) {
            return $this->actionSingle($params->attachment_id);
        }

        return $this->notFound();
    }

    public function actionDeleteIndex(ParameterBag $pb)
    {
        $params = $this->params()
            ->define('hash', 'str');

        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $this->assertRecordExists('XF:Attachment', $pb->attachment_id);

        $handler = $attachment->getHandler();
        if ($handler === null) {
            return $this->noPermission();
        }

        if (strlen($attachment->temp_hash) > 0) {
            if ($params['hash'] !== $attachment->temp_hash) {
                return $this->noPermission();
            }
        } else {
            $entity = $handler->getContainerEntity($attachment->content_id);
            $context = $handler->getContext($entity);
            $error = null;
            if (!$handler->canManageAttachments($context, $error)) {
                return $this->noPermission($error);
            }
        }

        $attachment->delete();

        return $this->message(\XF::phrase('changes_saved'));
    }

    protected function actionSingle($attachmentId)
    {
        /** @var \XF\Entity\Attachment|null $attachment */
        $attachment = $this->em()->find('XF:Attachment', $attachmentId);
        if (!$attachment) {
            throw $this->exception($this->notFound());
        }

        if ($attachment->temp_hash) {
            $hash = $this->filter('hash', 'str');
            if ($attachment->temp_hash !== $hash) {
                return $this->noPermission();
            }
        } else {
            if (!$attachment->canView($error)) {
                return $this->noPermission($error);
            }
        }

        /** @var \XF\ControllerPlugin\Attachment $attachPlugin */
        $attachPlugin = $this->plugin('XF:Attachment');

        return $attachPlugin->displayAttachment($attachment);
    }
}
