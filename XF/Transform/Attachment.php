<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;

class Attachment extends AbstractHandler
{
    const KEY_DOWNLOAD_COUNT = 'attachment_download_count';
    const KEY_FILENAME = 'filename';
    const KEY_ID = 'attachment_id';

    const LINK_DATA = 'data';
    const LINK_THUMBNAIL = 'thumbnail';

    public function calculateDynamicValue($context, $key)
    {
        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler) {
            return $parentHandler->attachmentCalculateDynamicValue($context, $key);
        }

        return parent::calculateDynamicValue($context, $key);
    }

    public function collectLinks($context)
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $context->getSource();

        $links = [
            self::LINK_DATA => $this->buildApiLink('attachments', $attachment, ['hash' => $attachment->temp_hash]),
            self::LINK_PERMALINK => $this->buildPublicLink('attachments', $attachment),
        ];

        $thumbnailUrl = $attachment->thumbnail_url;
        if (!empty($thumbnailUrl)) {
            $links[self::LINK_THUMBNAIL] = $thumbnailUrl;
        }

        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler) {
            $parentHandler->attachmentCollectLinks($context, $links);
        }

        return $links;
    }

    public function collectPermissions($context)
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $context->getSource();

        $permissions = [
            self::PERM_DELETE => false,
            self::PERM_VIEW => $attachment->canView(),
        ];

        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler) {
            $parentHandler->attachmentCollectPermissions($context, $permissions);
        }

        return $permissions;
    }

    public function getMappings($context)
    {
        $mappings = [
            'attachment_id' => self::KEY_ID,
            'filename' => self::KEY_FILENAME,
            'view_count' => self::KEY_DOWNLOAD_COUNT,
        ];

        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler) {
            $parentHandler->attachmentGetMappings($context, $mappings);
        }

        return $mappings;
    }
}
