<?php

namespace Xfrocks\Api\XF\Transformer;

use Xfrocks\Api\Transformer\AbstractHandler;

class Attachment extends AbstractHandler
{
    const KEY_DOWNLOAD_COUNT = 'attachment_download_count';
    const KEY_FILENAME = 'filename';
    const KEY_ID = 'attachment_id';

    const LINK_DATA = 'data';
    const LINK_THUMBNAIL = 'thumbnail';

    public function collectLinks()
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $this->entity;

        $links = [
            self::LINK_DATA => $this->buildApiLink('attachments', $attachment, ['hash' => $attachment->temp_hash]),
            self::LINK_PERMALINK => $this->buildPublicLink('attachments', $attachment),
        ];

        $thumbnailUrl = $attachment->thumbnail_url;
        if (!empty($thumbnailUrl)) {
            $links[self::LINK_THUMBNAIL] = $thumbnailUrl;
        }

        return $links;
    }

    public function collectPermissions()
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $this->entity;

        return [
            self::PERM_DELETE => false,
            self::PERM_VIEW => $attachment->canView(),
        ];
    }

    public function getMappings()
    {
        return [
            'attachment_id' => self::KEY_ID,
            'filename' => self::KEY_FILENAME,
            'view_count' => self::KEY_DOWNLOAD_COUNT,
        ];
    }

    public function postTransform(array &$data)
    {
        if ($this->parent) {
            if (!$this->parent->postTransformAttachment($data)) {
                return false;
            }
        }

        return true;
    }
}
