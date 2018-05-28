<?php

namespace Xfrocks\Api\Controller;

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

    protected function actionSingle($attachmentId)
    {
        /** @var \XF\Entity\Attachment $attachment */
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
