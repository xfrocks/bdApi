<?php

namespace Xfrocks\Api\XFMG\Controller;


use XF\Mvc\ParameterBag;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class Album extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->album_id) {
            return $this->actionSingle($params->album_id);
        }

        $params = $this->params()
            ->define('category_id', 'int', '', -1)
            ->define('user_id', 'uint')
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
        $this->applyFilters($finder, $params);
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

    public function actionPostIndex(ParameterBag $params)
    {
        /** @var \XFMG\XF\Entity\User $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canCreateAlbum())
        {
            return $this->noPermission();
        }

        $params = $this->defineAlbumParams()
            ->define('category_id', 'uint', 'category of the new album')
        ;

        /** @var \XFMG\Service\Album\Creator $creator */
        $creator = $this->service('XFMG:Album\Creator');

        if (!empty($params['category_id'])) {
            $category = $this->assertViewableCategory($params['category_id']);
            if (!$category->canCreateAlbum())
            {
                return $this->noPermission();
            }
            $creator->setCategory($category);
        }
        $creator->setTitle($params['title'], $params['description']);
        $creator->setViewPrivacy($params['view_privacy'], $params['view_users']);
        $creator->setAddPrivacy($params['add_privacy'], $params['add_users']);

        $creator->checkForSpam();

        if (!$creator->validate($errors))
        {
            return $this->error($errors);
        }
        $album = $creator->save();

        $creator->sendNotifications();

        /** @var \XFMG\Repository\AlbumWatch $watchRepo */
        $watchRepo = $this->repository('XFMG:AlbumWatch');
        $watchRepo->autoWatchAlbum($album, \XF::visitor(), true);

        return $this->actionSingle($album->album_id);
    }

    public function actionPutIndex(ParameterBag $params)
    {
        $album = $this->assertViewableAlbum($params->album_id);
        if (!$album->canEdit($error))
        {
            return $this->noPermission($error);
        }

        $params = $this->defineAlbumParams();
        $title = $params['title'];
        $description = $params['description'];

        /** @var \XFMG\Service\Album\Editor $editor */
        $editor = $this->service('XFMG:Album\Editor', $album);

        if (!empty($title) && !empty($description)) {
            $editor->setTitle($title, $description);
        }
        if ($album->canChangePrivacy())
        {
            $editor->setViewPrivacy($params['view_privacy'], $params['view_users']);
            $editor->setAddPrivacy($params['add_privacy'], $params['add_users']);
        }

        $editor->checkForSpam();

        if (!$editor->validate($errors))
        {
            return $this->error($errors);
        }
        $editor->save();

        return $this->actionSingle($album->album_id);
    }

    public function actionDeleteIndex(ParameterBag $params)
    {
        $album = $this->assertViewableAlbum($params->album_id);
        if (!$album->canDelete('soft', $error))
        {
            return $this->noPermission($error);
        }

        /** @var \XFMG\Service\Album\Deleter $deleter */
        $deleter = $this->service('XFMG:Album\Deleter', $album);
        $deleter->delete('soft');

        return $this->actionSingle($album->album_id);
    }

    public function actionPostLikes(ParameterBag $params)
    {
        $album = $this->assertViewableAlbum($params->album_id);
        if (!$album->canLike($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (empty($album->Likes[$visitor->user_id])) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $contentType = $album->getEntityContentType();
            $likeRepo->toggleLike($contentType, $album->album_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionDeleteLikes(ParameterBag $params)
    {
        $album = $this->assertViewableAlbum($params->album_id);
        if (!$album->canLike($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (!empty($album->Likes[$visitor->user_id])) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $contentType = $album->getEntityContentType();
            $likeRepo->toggleLike($contentType, $album->album_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionPostFollowers(ParameterBag $params)
    {
        $album = $this->assertViewableAlbum($params->album_id);
        if (!$album->canWatch($error)) {
            return $this->noPermission($error);
        }

        $params = $this->params()
            ->define('notify_on', 'str', 'comment|media|media_comment', 'media_comment')
            ->define('send_alert', 'bool', '', true)
            ->define('send_email', 'bool', '', true)
        ;

        $action = 'watch';
        $config = [
            'notify_on' => $params['notify_on'],
            'send_alert' => $params['send_alert'],
            'send_email' => $params['send_email'],
        ];
        $visitor = \XF::visitor();

        /** @var \XFMG\Repository\AlbumWatch $watchRepo */
        $watchRepo = $this->repository('XFMG:AlbumWatch');
        $watchRepo->setWatchState($album, $visitor, $action, $config);

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionDeleteFollowers(ParameterBag $params)
    {
        $album = $this->assertViewableAlbum($params->album_id);

        $visitor = \XF::visitor();

        /** @var \XFMG\Repository\AlbumWatch $watchRepo */
        $watchRepo = $this->repository('XFMG:AlbumWatch');
        $watchRepo->setWatchState($album, $visitor, 'delete');

        return $this->message(\XF::phrase('changes_saved'));
    }

    protected function defineAlbumParams()
    {
        return $this->params()
            ->define('title', 'str', 'new title of the album')
            ->define('description', 'str', 'new description of the album')
            ->define('view_privacy', 'str', 'public, members, or private', 'public')
            ->define('view_users', 'str', 'specific users who can view this album', null)
            ->define('add_privacy', 'str', 'public, members, or private', 'private')
            ->define('add_users', 'str', 'specific users who can add media to this album', null)
        ;
    }

    protected function applyFilters(\XFMG\Finder\Album $finder, Params $params)
    {
        if ($params['category_id'] > -1) {
            $finder->inCategory($params['category_id']);
        }
        if ($params['user_id'] > 0) {
            $finder->byUser($params['user_id']);
        }
    }
}
