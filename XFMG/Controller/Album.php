<?php

namespace Xfrocks\Api\XFMG\Controller;


use XF\Mvc\ParameterBag;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Util\PageNav;

class Album extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->album_id) {
            return $this->actionSingle($params->album_id);
        }

        $params = $this->params()
            ->defineOrder([
                'natural' => ['create_date', 'asc'],
                'natural_reverse' => ['create_date', 'desc'],
                'album_last_update_date' => ['last_update_date', 'asc'],
                'album_last_update_date_reverse' => ['last_update_date', 'desc'],
                'album_rating' => ['rating_avg', 'asc'],
                'album_rating_reverse' => ['rating_avg', 'desc'],
                'album_comment_count' => ['comment_count', 'asc'],
                'album_comment_count_reverse' => ['comment_count', 'desc'],
            ])
            ->definePageNav();

        /** @var \XFMG\Finder\Album $finder */
        $finder = $this->finder('XFMG:Album');
        $params->sortFinder($finder);
        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $albums = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'albums' => $albums,
            'albums_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'albums');

        return $this->api($data);
    }

    protected function actionSingle($albumId)
    {
        $album = $this->assertViewableAlbum($albumId);

        $data = [
            'album' => $this->transformEntityLazily($album)
        ];

        return $this->api($data);
    }

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
}
