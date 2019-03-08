<?php
/**
 * Created by PhpStorm.
 * User: datbth
 * Date: 01/12/2018
 * Time: 10:50
 */

namespace Xfrocks\Api\XFMG\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class Album extends AbstractHandler
{
    const KEY_ID = 'album_id';
    const KEY_CATEGORY_ID = 'category_id';
    const KEY_TITLE = 'title';
    const KEY_DESCRIPTION = 'description';
    const KEY_CREATE_DATE = 'create_date';
    const KEY_LAST_UPDATE_DATE = 'last_update_date';
    const KEY_USER_ID = 'user_id';
    const KEY_USERNAME = 'username';
    const KEY_LIKE_COUNT = 'like_count';
    const KEY_MEDIA_COUNT = 'media_count';
    const KEY_VIEW_COUNT = 'view_count';
    const KEY_RATING_COUNT = 'rating_count';
    const KEY_RATING = 'rating';
    const KEY_COMMENT_COUNT = 'comment_count';

    const DYNAMIC_KEY_IS_LIKED = 'is_liked';
    const DYNAMIC_KEY_IS_FOLLOWED = 'is_followed';
    const DYNAMIC_KEY_IS_DELETED = 'is_deleted';

    const PERM_ADD_MEDIA = 'add_media';
    const PERM_COMMENT = 'comment';

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XFMG\Entity\Album $album */
        $album = $context->getSource();
        switch ($key) {
            case self::DYNAMIC_KEY_IS_LIKED:
                return $album->isLiked();
            case self::DYNAMIC_KEY_IS_FOLLOWED:
                return !empty($album->Watch[\XF::visitor()->user_id]);
            case self::DYNAMIC_KEY_IS_DELETED:
                return $album->album_state == 'deleted';
        }
        return null;
    }

    public function collectPermissions($context)
    {
        /** @var \XFMG\Entity\Album $album */
        $album = $context->getSource();

        $permissions = [
            self::PERM_DELETE => $album->canDelete(),
            self::PERM_EDIT => $album->canEdit(),
            self::PERM_LIKE => $album->canLike(),
            self::PERM_FOLLOW => $album->canWatch(),
            self::PERM_ADD_MEDIA => $album->canAddMedia(),
            self::PERM_COMMENT => $album->canAddComment(),
        ];

        return $permissions;
    }

    public function collectLinks($context)
    {
        /** @var \XFMG\Entity\Album $album */
        $album = $context->getSource();

        $links = [
            self::LINK_PERMALINK => $this->buildPublicLink('media/albums', $album),
            self::LINK_DETAIL => $this->buildApiLink('media/albums', $album),
            self::LINK_LIKES => $this->buildApiLink('media/albums/likes', $album),
            self::LINK_FOLLOWERS => $this->buildApiLink('media/albums/followers', $album),
        ];

        return $links;
    }

    public function getMappings($context)
    {
        return [
            'album_id' => self::KEY_ID,
            'category_id' => self::KEY_CATEGORY_ID,
            'title' => self::KEY_TITLE,
            'description' => self::KEY_DESCRIPTION,
            'user_id' => self::KEY_USER_ID,
            'username' => self::KEY_USERNAME,
            'create_date' => self::KEY_CREATE_DATE,
            'last_update_date' => self::KEY_LAST_UPDATE_DATE,
            'media_count' => self::KEY_MEDIA_COUNT,
            'likes' => self::KEY_LIKE_COUNT,
            'comment_count' => self::KEY_COMMENT_COUNT,
            'rating_count' => self::KEY_RATING_COUNT,
            'rating_avg' => self::KEY_RATING,

            self::DYNAMIC_KEY_IS_LIKED,
            self::DYNAMIC_KEY_IS_FOLLOWED,
            self::DYNAMIC_KEY_IS_DELETED,
        ];
    }
}