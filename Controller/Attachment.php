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

    public function actionDeleteIndex(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('temp_hash', 'str');

        /** @var \XF\Entity\Attachment|null $attachment */
        $attachment = $this->em()->find('XF:Attachment', $paramBag->attachment_id);
        if (!$attachment) {
            return $this->notFound();
        }

        $handler = $attachment->getHandler();
        if (!$handler) {
            return $this->noPermission();
        }

        $context = [];
        $error = null;
        if (!$handler->canManageAttachments($context, $error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Repository\Attachment $attachRepo */
        $attachRepo = $this->repository('XF:Attachment');

        $manipulator = new Manipulator($handler, $attachRepo, $context, $params['temp_hash']);
        $manipulator->deleteAttachment($attachment->attachment_id);

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
