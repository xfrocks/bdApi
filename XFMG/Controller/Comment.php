<?php
/**
 * Created by PhpStorm.
 * User: datbth
 * Date: 09/12/2018
 * Time: 10:46
 */

namespace Xfrocks\Api\XFMG\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class Comment extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->comment_id) {
            return $this->actionSingle($params->comment_id);
        }

        $params = $this->params()
            ->define('media_id', 'int', '')
            ->define('album_id', 'int', '')
            ->defineOrder([
                'natural' => ['comment_date', 'asc'],
                'natural_reverse' => ['comment_date', 'desc'],
            ])
            ->definePageNav();

        $content = $this->assertViewableContent($params);

        /** @var \XFMG\Finder\Comment $finder */
        $finder = $this->finder('XFMG:Comment');
        $finder->forContent($content);
        $params->sortFinder($finder);
        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $comments = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'comments' => $comments,
            'comments_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'comments');

        return $this->api($data);
    }

    protected function actionSingle($commentId)
    {
        $comment = $this->assertViewableComment($commentId);

        $data = [
            'comment' => $this->transformEntityLazily($comment)
        ];

        return $this->api($data);
    }

    public function actionPostIndex(ParameterBag $params)
    {
        $params = $this->params()
            ->define('media_id', 'int', '')
            ->define('album_id', 'int', '')
            ->define('message', 'str', 'comment message')
        ;

        $content = $this->assertViewableAndCommentableContent($params);

        /** @var \XFMG\Service\Comment\Creator $creator */
        $creator = $this->service('XFMG:Comment\Creator', $content);
        $creator->setMessage($params['message']);
        $creator->checkForSpam();

        if (!$creator->validate($errors))
        {
            return $this->error($errors);
        }
        /** @var \XFMG\Entity\Comment $comment */
        $comment = $creator->save();

        $this->finalizeCommentCreate($creator);

        return $this->actionSingle($comment->comment_id);
    }

    public function actionPutIndex(ParameterBag $params)
    {
        $comment = $this->assertViewableComment($params->comment_id);
        if (!$comment->canEdit($error)) {
            return $this->noPermission($error);
        }

        $params = $this->params()
            ->define('message', 'str', 'comment message')
        ;

        /** @var \XFMG\Service\Comment\Editor $editor */
        $editor = $this->service('XFMG:Comment\Editor', $comment);
        $editor->setMessage($params['message']);
        $editor->checkForSpam();

        if (!$editor->validate($errors))
        {
            return $this->error($errors);
        }
        /** @var \XFMG\Entity\Comment $comment */
        $comment = $editor->save();

        return $this->actionSingle($comment->comment_id);
    }

    public function actionDeleteIndex(ParameterBag $params)
    {
        $comment = $this->assertViewableComment($params->comment_id);
        if (!$comment->canDelete('soft', $error)) {
            return $this->noPermission($error);
        }

        /** @var \XFMG\Service\Comment\Deleter $deleter */
        $deleter = $this->service('XFMG:Comment\Deleter', $comment);
        $deleter->delete('soft');

        return $this->actionSingle($comment->comment_id);
    }

    public function actionPostLikes(ParameterBag $params)
    {
        $comment = $this->assertViewableComment($params->comment_id);
        if (!$comment->canLike($error)) {
            return $this->noPermission($error);
        }

        if (!$comment->isLiked()) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $contentType = $comment->getEntityContentType();
            $likeRepo->toggleLike($contentType, $comment->comment_id, \XF::visitor());
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionDeleteLikes(ParameterBag $params)
    {
        $comment = $this->assertViewableComment($params->comment_id);
        if (!$comment->canLike($error)) {
            return $this->noPermission($error);
        }

        if ($comment->isLiked()) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $contentType = $comment->getEntityContentType();
            $likeRepo->toggleLike($contentType, $comment->comment_id, \XF::visitor());
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    protected function finalizeCommentCreate(\XFMG\Service\Comment\Creator $creator)
    {
        $creator->sendNotifications();

        $content = $creator->getContent();
        $content->draft_comment->delete();

        $visitor = \XF::visitor();

        if ($visitor->user_id != $content->user_id)
        {
            if ($content->content_type == 'xfmg_media')
            {
                /** @var \XFMG\Repository\MediaWatch $watchRepo */
                $watchRepo = $this->repository('XFMG:MediaWatch');
                $watchRepo->autoWatchMediaItem($content, $visitor);
            }
            else
            {
                /** @var \XFMG\Repository\AlbumWatch $watchRepo */
                $watchRepo = $this->repository('XFMG:AlbumWatch');
                $watchRepo->autoWatchAlbum($content, $visitor);
            }
        }
    }

    protected function assertViewableContent(Params $params)
    {
        if ($params['media_id'] > 0) {
            $content = $this->assertViewableMediaItem($params['media_id']);
        } elseif ($params['album_id'] > 0) {
            $content = $this->assertViewableAlbum($params['album_id']);
        } else {
            throw $this->exception($this->noPermission());
        }
        if (!$content->canViewComments($error)) {
            throw $this->exception($this->noPermission($error));
        }
        return $content;
    }

    protected function assertViewableAndCommentableContent(Params $params)
    {
        $content = $this->assertViewableContent($params);
        if (!$content->canAddComment($error)) {
            throw $this->exception($this->noPermission($error));
        }
        return $content;
    }

    protected function assertViewableComment($commentId, array $extraWith = [])
    {
        /** @var \XFMG\Entity\Comment $comment */
        $comment = $this->assertRecordExists(
            'XFMG:Comment',
            $commentId,
            $extraWith,
            'xfmg_requested_comment_not_found'
        );

        if (!$comment->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $comment;
    }
}
