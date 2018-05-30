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

    public function calculateDynamicValue($key)
    {
        if ($this->parent instanceof AttachmentParent) {
            return $this->parent->attachmentCalculateDynamicValue($this, $key);
        }

        return null;
    }

    public function collectLinks()
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $this->source;

        $links = [
            self::LINK_DATA => $this->buildApiLink('attachments', $attachment, ['hash' => $attachment->temp_hash]),
            self::LINK_PERMALINK => $this->buildPublicLink('attachments', $attachment),
        ];

        $thumbnailUrl = $attachment->thumbnail_url;
        if (!empty($thumbnailUrl)) {
            $links[self::LINK_THUMBNAIL] = $thumbnailUrl;
        }

        if ($this->parent instanceof AttachmentParent) {
            $this->parent->attachmentCollectLinks($this, $links);
        }

        return $links;
    }

    public function collectPermissions()
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $this->source;

        $permissions = [
            self::PERM_DELETE => false,
            self::PERM_VIEW => $attachment->canView(),
        ];

        if ($this->parent instanceof AttachmentParent) {
            $this->parent->attachmentCollectPermissions($this, $permissions);
        }

        return $permissions;
    }

    public function getMappings()
    {
        $mappings = [
            'attachment_id' => self::KEY_ID,
            'filename' => self::KEY_FILENAME,
            'view_count' => self::KEY_DOWNLOAD_COUNT,
        ];

        if ($this->parent instanceof AttachmentParent) {
            $this->parent->attachmentGetMappings($this, $mappings);
        }

        return $mappings;
    }
}
