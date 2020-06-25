<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;
use Xfrocks\Api\Transform\TransformContext;

class Attachment extends AbstractHandler
{
    const KEY_DOWNLOAD_COUNT = 'attachment_download_count';
    const KEY_FILENAME = 'filename';
    const KEY_HEIGHT = 'attachment_height';
    const KEY_IS_INSERTED = 'attachment_is_inserted';
    const KEY_ID = 'attachment_id';
    const KEY_WIDTH = 'attachment_width';

    const LINK_DATA = 'data';
    const LINK_THUMBNAIL = 'thumbnail';

    public function calculateDynamicValue(TransformContext $context, $key)
    {
        switch ($key) {
            case self::KEY_HEIGHT:
            case self::KEY_WIDTH:
                /** @var \XF\Entity\Attachment $attachment */
                $attachment = $context->getSource();
                $data = $attachment->Data;
                if ($data !== null && $data->height > 0 && $data->width > 0) {
                    return $key == self::KEY_HEIGHT ? $data->height : $data->width;
                }

                return null;
            case self::KEY_IS_INSERTED:
                $parentSource = $context->getParentSource();
                $isAttachmentEmbedded = [$parentSource, 'isAttachmentEmbedded'];
                if (is_callable($isAttachmentEmbedded)) {
                    /** @var \XF\Entity\Attachment $attachment */
                    $attachment = $context->getSource();
                    return call_user_func($isAttachmentEmbedded, $attachment->attachment_id);
                }

                return null;
        }

        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler !== null) {
            return $parentHandler->attachmentCalculateDynamicValue($context, $key);
        }

        return parent::calculateDynamicValue($context, $key);
    }

    public function canView(TransformContext $context)
    {
        return true;
    }

    public function collectLinks(TransformContext $context)
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $context->getSource();

        $links = [
            self::LINK_DATA => $this->buildApiLink('attachments', $attachment, ['hash' => $attachment->temp_hash]),
            self::LINK_PERMALINK => $this->buildPublicLink('attachments', $attachment),
        ];

        $thumbnailUrl = $attachment->thumbnail_url;
        if ($thumbnailUrl !== '') {
            $links[self::LINK_THUMBNAIL] = $thumbnailUrl;
        }

        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler !== null) {
            $parentHandler->attachmentCollectLinks($context, $links);
        }

        return $links;
    }

    public function collectPermissions(TransformContext $context)
    {
        /** @var \XF\Entity\Attachment $attachment */
        $attachment = $context->getSource();

        $permissions = [
            self::PERM_DELETE => false,
            self::PERM_VIEW => $attachment->canView(),
        ];

        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler !== null) {
            $parentHandler->attachmentCollectPermissions($context, $permissions);
        }

        return $permissions;
    }

    public function getMappings(TransformContext $context)
    {
        $mappings = [
            'attachment_id' => self::KEY_ID,
            'filename' => self::KEY_FILENAME,
            'view_count' => self::KEY_DOWNLOAD_COUNT,

            self::KEY_HEIGHT,
            self::KEY_IS_INSERTED,
            self::KEY_WIDTH,
        ];

        /** @var AttachmentParent|null $parentHandler */
        $parentHandler = $context->getParentHandler();
        if ($parentHandler !== null) {
            $parentHandler->attachmentGetMappings($context, $mappings);
        }

        return $mappings;
    }
}
