<?php

namespace Xfrocks\Api\XFMG\Controller;


use XF\Mvc\ParameterBag;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Util\PageNav;

class Media extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->media_id) {
            return $this->actionSingle($params->media_id);
        }

        $params = $this->params()
            ->defineOrder([
                'natural' => ['media_date', 'asc'],
                'natural_reverse' => ['media_date', 'desc'],
                'media_rating' => ['rating_avg', 'asc'],
                'media_rating_reverse' => ['rating_avg', 'desc'],
                'media_comment_count' => ['comment_count', 'asc'],
                'media_comment_count_reverse' => ['comment_count', 'desc'],
            ])
            ->definePageNav();

        /** @var \XFMG\Finder\MediaItem $finder */
        $finder = $this->finder('XFMG:MediaItem');
        $params->sortFinder($finder);
        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $items = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'items' => $items,
            'items_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'items');

        return $this->api($data);
    }

    protected function actionSingle($itemId)
    {
        $item = $this->assertViewableItem($itemId);

        $data = [
            'item' => $this->transformEntityLazily($item)
        ];

        return $this->api($data);
    }

    /**
     * @param int $mediaId
     * @param array $extraWith
     * @return \XFMG\Entity\MediaItem
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableItem($mediaId, array $extraWith = [])
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
