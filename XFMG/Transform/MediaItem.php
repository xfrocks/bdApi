<?php
/**
 * Created by PhpStorm.
 * User: datbth
 * Date: 01/12/2018
 * Time: 14:38
 */

namespace Xfrocks\Api\XFMG\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\AttachmentParent;

class MediaItem extends AbstractHandler implements AttachmentParent
{
    const ATTACHMENT__DYNAMIC_KEY_ID = 'media_id';
    const ATTACHMENT__LINK_MEDIA = 'media';
    const ATTACHMENT__LINK_THUMBNAIL = 'thumbnail';

    const KEY_ID = 'media_id';
    const KEY_MEDIA_TYPE = 'media_type';
    const KEY_ALBUM_ID = 'album_id';
    const KEY_TITLE = 'title';
    const KEY_DESCRIPTION = 'description';
    const KEY_EXIF_DATA = 'exif_data';
    const KEY_MEDIA_DATE = 'media_date';
    const KEY_LAST_EDIT_DATE = 'last_edit_date';
    const KEY_USER_ID = 'user_id';
    const KEY_USERNAME = 'username';
    const KEY_LIKE_COUNT = 'like_count';
    const KEY_VIEW_COUNT = 'view_count';
    const KEY_RATING_COUNT = 'rating_count';
    const KEY_RATING = 'rating';
    const KEY_COMMENT_COUNT = 'comment_count';

    const LINK_ALBUM = 'album';

    const DYNAMIC_KEY_ATTACHMENT = 'attachment';
    const DYNAMIC_KEY_IS_LIKED = 'is_liked';
    const DYNAMIC_KEY_IS_FOLLOWED = 'is_followed';
    const DYNAMIC_KEY_IS_DELETED = 'is_deleted';

    public function attachmentCalculateDynamicValue($context, $key)
    {
        switch ($key) {
            case self::ATTACHMENT__DYNAMIC_KEY_ID:
                return $context->getParentSourceValue('media_id');
        }

        return null;
    }

    public function attachmentCollectLinks($context, array &$links)
    {
        /** @var \XFMG\Entity\MediaItem $item */
        $item = $context->getParentSource();
        $links[self::ATTACHMENT__LINK_MEDIA] = $this->buildApiLink('media', $item);
        $links[self::ATTACHMENT__LINK_THUMBNAIL] = $item->getCurrentThumbnailUrl();
    }

    public function attachmentCollectPermissions($context, array &$permissions)
    {
    }

    public function attachmentGetMappings($context, array &$mappings)
    {
        $mappings[] = self::ATTACHMENT__DYNAMIC_KEY_ID;
    }

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XFMG\Entity\MediaItem $item */
        $item = $context->getSource();
        switch ($key) {
            case self::DYNAMIC_KEY_ATTACHMENT:
                if (!$item->Attachment) {
                    return null;
                }

                return $this->transformer->transformEntityRelation($context, $key, $item, 'Attachment');
            case self::DYNAMIC_KEY_IS_LIKED:
                return $item->isLiked();
            case self::DYNAMIC_KEY_IS_FOLLOWED:
                return !empty($item->Watch[\XF::visitor()->user_id]);
            case self::DYNAMIC_KEY_IS_DELETED:
                return $item->media_state == 'deleted';
        }
        return null;
    }

    public function collectLinks($context)
    {
        /** @var \XFMG\Entity\MediaItem $item */
        $item = $context->getSource();

        $links = [
            self::LINK_PERMALINK => $this->buildPublicLink('media', $item),
            self::LINK_DETAIL => $this->buildApiLink('media', $item),
            self::LINK_LIKES => $this->buildApiLink('media/likes', $item),
            self::LINK_FOLLOWERS => $this->buildApiLink('media/followers', $item),
            self::LINK_ALBUM => $this->buildPublicLink('media', $item),
        ];

        return $links;
    }

    public function getMappings($context)
    {
        return [
            'media_id' => self::KEY_ID,
            'media_type' => self::KEY_MEDIA_TYPE,
            'title' => self::KEY_TITLE,
            'description' => self::KEY_DESCRIPTION,
            'exif_data' => self::KEY_EXIF_DATA,
            'album_id' => self::KEY_ALBUM_ID,
            'user_id' => self::KEY_USER_ID,
            'username' => self::KEY_USERNAME,
            'media_date' => self::KEY_MEDIA_DATE,
            'last_edit_date' => self::KEY_LAST_EDIT_DATE,
            'likes' => self::KEY_LIKE_COUNT,
            'comment_count' => self::KEY_COMMENT_COUNT,
            'rating_count' => self::KEY_RATING_COUNT,
            'rating_avg' => self::KEY_RATING,

            self::DYNAMIC_KEY_ATTACHMENT,
            self::DYNAMIC_KEY_IS_LIKED,
            self::DYNAMIC_KEY_IS_FOLLOWED,
            self::DYNAMIC_KEY_IS_DELETED,
        ];
    }
}
