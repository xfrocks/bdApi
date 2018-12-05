<?php
/**
 * Created by PhpStorm.
 * User: datbth
 * Date: 05/12/2018
 * Time: 21:39
 */

namespace Xfrocks\Api\XFMG\Controller;

abstract class AbstractController extends \Xfrocks\Api\Controller\AbstractController
{
    /**
     * @param int $albumId
     * @param array $extraWith
     * @return \XFMG\Entity\Album
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableAlbum($albumId, array $extraWith = [])
    {
        /** @var \XFMG\Entity\Album $album */
        $album = $this->assertRecordExists(
            'XFMG:Album',
            $albumId,
            $extraWith,
            'xfmg_requested_album_not_found'
        );

        if (!$album->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $album;
    }

    /**
     * @param int $categoryId
     * @param array $extraWith
     * @return \XFMG\Entity\Category
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableCategory($categoryId, array $extraWith = [])
    {
        /** @var \XFMG\Entity\Category $category */
        $category = $this->assertRecordExists(
            'XFMG:Category',
            $categoryId,
            $extraWith,
            'xfmg_requested_category_not_found'
        );

        if (!$category->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $category;
    }

    /**
     * @param int $mediaId
     * @param array $extraWith
     * @return \XFMG\Entity\MediaItem
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableMediaItem($mediaId, array $extraWith = [])
    {
        /** @var \XFMG\Entity\MediaItem $item */
        $item = $this->assertRecordExists(
            'XFMG:MediaItem',
            $mediaId,
            $extraWith,
            'xfmg_requested_media_item_not_found'
        );

        if (!$item->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $item;
    }
}