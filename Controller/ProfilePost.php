<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\ProfilePostComment;
use XF\Mvc\ParameterBag;
use XF\Service\ProfilePostComment\Creator;
use XF\Service\ProfilePostComment\Deleter;
use Xfrocks\Api\Util\BackwardCompat21;

class ProfilePost extends AbstractController
{
    /**
     * @param ParameterBag $paramBag
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetIndex(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('profile_post_ids', 'str');

        if (!empty($params['profile_post_ids'])) {
            $profilePostIds = $params->filterCommaSeparatedIds('profile_post_ids');

            return $this->actionMultiple($profilePostIds);
        }

        return $this->actionSingle($paramBag->profile_post_id);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPutIndex(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('post_body', 'str', 'new content of the profile post');

        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);
        if (!$profilePost->canEdit($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Service\ProfilePost\Editor $editor */
        $editor = $this->service('XF:ProfilePost\Editor', $profilePost);
        $editor->setMessage($params['post_body']);

        if (!$editor->validate($errors)) {
            return $this->error($errors);
        }

        $profilePost = $editor->save();

        return $this->actionSingle($profilePost->profile_post_id);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteIndex(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('reason', 'str');

        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);
        if (!$profilePost->canDelete('soft', $error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Service\ProfilePost\Deleter $deleter */
        $deleter = $this->service('XF:ProfilePost\Deleter', $profilePost);
        $deleter->delete('soft', $params['reason']);

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $paramBag
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetLikes(ParameterBag $paramBag)
    {
        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        $finder = $profilePost->getRelationFinder(BackwardCompat21::getLikesRelation());
        $finder->with(BackwardCompat21::getLikerRelation());

        $users = [];

        /** @var \XF\Mvc\Entity\Entity $liked */
        foreach ($finder->fetch() as $liked) {
            /** @var \XF\Entity\User $liker */
            $liker = $liked->getRelation(BackwardCompat21::getLikerRelation());

            $users[] = [
                'user_id' => $liker->user_id,
                'username' => $liker->username
            ];
        }

        $data = ['users' => $users];
        return $this->api($data);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostLikes(ParameterBag $paramBag)
    {
        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        if (!BackwardCompat21::canLike($profilePost, $error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (!BackwardCompat21::isLiked($profilePost)) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('profile_post', $profilePost->profile_post_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteLikes(ParameterBag $paramBag)
    {
        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        if (!BackwardCompat21::canLike($profilePost, $error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (BackwardCompat21::isLiked($profilePost)) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('profile_post', $profilePost->profile_post_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $paramBag
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetComments(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('before', 'uint')
            ->define('page_of_comment_id', 'uint')
            ->define('comment_id', 'uint')
            ->definePageNav();

        /** @var ProfilePostComment|null $comment */
        $comment = null;
        if ($params['page_of_comment_id'] > 0) {
            $comment = $this->assertViewableComment($params['page_of_comment_id']);
            $profilePost = $comment->ProfilePost;
        } else {
            $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

            if ($params['comment_id'] > 0) {
                $comment = $this->assertViewableComment($params['comment_id']);
                if ($comment->profile_post_id != $profilePost->profile_post_id) {
                    return $this->noPermission();
                }

                $data = [
                    'comment' => $this->transformEntityLazily($comment)
                ];

                return $this->api($data);
            }
        }

        $beforeDate = $params['before'];
        if ($comment) {
            $beforeDate = $comment->comment_date + 1;
        }

        list($limit,) = $params->filterLimitAndPage();

        /** @var \XF\Repository\ProfilePost $profilePostRepo */
        $profilePostRepo = $this->repository('XF:ProfilePost');
        $finder = $profilePostRepo->findNewestCommentsForProfilePost($profilePost, $beforeDate);

        $finder->limit($limit);

        $comments = $finder->fetch()->reverse(true);

        /** @var ProfilePostComment|false $oldestComment */
        $oldestComment = $comments->first();
        /** @var ProfilePostComment|false $latestComment */
        $latestComment = $comments->last();

        $data = [
            'comments' => [],
            'comment_total' => $profilePost->comment_count,
            'links' => [],
            'profile_post' => $this->transformEntityLazily($profilePost),
            'timeline_user' => $this->transformEntityLazily($profilePost->ProfileUser)
        ];

        foreach ($comments as $comment) {
            $data['comments'][] = $this->transformEntityLazily($comment);
        }

        if ($oldestComment && $oldestComment->comment_date != $profilePost->first_comment_date) {
            $data['links']['prev'] = $this->buildApiLink(
                'profile-posts/comments',
                $profilePost,
                ['before' => $oldestComment->comment_date]
            );
        }

        if ($latestComment && $latestComment->comment_date != $profilePost->last_comment_date) {
            $data['links']['latest'] = $this->buildApiLink(
                'profile-posts/comments',
                $profilePost
            );
        }

        return $this->api($data);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Reroute
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostComments(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('comment_body', 'str');

        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        if (!$profilePost->canComment($error)) {
            return $this->noPermission($error);
        }

        /** @var Creator $creator */
        $creator = $this->service('XF:ProfilePostComment\Creator', $profilePost);
        $creator->setContent($params['comment_body']);

        $creator->checkForSpam();

        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $this->assertNotFlooding('post');

        /** @var ProfilePostComment $comment */
        $comment = $creator->save();
        $creator->sendNotifications();

        $this->request()->set('comment_id', $comment->profile_post_comment_id);
        return $this->rerouteController(__CLASS__, 'get-comments', [
            'profile_post_id' => $profilePost->profile_post_id
        ]);
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteComments(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('comment_id', 'uint')
            ->define('reason', 'str');

        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);
        $comment = $this->assertViewableComment($params['comment_id']);

        if ($comment->profile_post_id != $profilePost->profile_post_id) {
            return $this->noPermission();
        }

        if (!$comment->canDelete('soft', $error)) {
            return $this->noPermission($error);
        }

        /** @var Deleter $deleter */
        $deleter = $this->service('XF:ProfilePostComment\Deleter', $comment);
        $deleter->delete('soft', $params['reason']);

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $paramBag
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostReport(ParameterBag $paramBag)
    {
        $params = $this->params()->define('message', 'str');

        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        if (!$profilePost->canReport($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Service\Report\Creator $creator */
        $creator = $this->service('XF:Report\Creator', 'profile_post', $profilePost);
        $creator->setMessage($params['message']);

        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $creator->save();

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionMultiple(array $ids)
    {
        $profilePosts = [];
        if (count($ids) > 0) {
            $profilePosts = $this->findAndTransformLazily('XF:ProfilePost', $ids);
        }

        return $this->api(['profile_posts' => $profilePosts]);
    }

    /**
     * @param int $profilePostId
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionSingle($profilePostId)
    {
        $profilePost = $this->assertViewableProfilePost($profilePostId);

        $data = [
            'profile_post' => $this->transformEntityLazily($profilePost)
        ];

        return $this->api($data);
    }

    /**
     * @param int $commentId
     * @param array $extraWith
     * @return \XF\Entity\ProfilePostComment
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableComment($commentId, array $extraWith = [])
    {
        $extraWith[] = 'User';
        $extraWith[] = 'ProfilePost.ProfileUser';
        $extraWith[] = 'ProfilePost.ProfileUser.Privacy';
        array_unique($extraWith);

        /** @var \XF\Entity\ProfilePostComment|null $comment */
        $comment = $this->em()->find('XF:ProfilePostComment', $commentId, $extraWith);
        if (!$comment) {
            throw $this->exception($this->notFound(\XF::phrase('requested_comment_not_found')));
        }
        if (!$comment->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $comment;
    }

    /**
     * @param int $profilePostId
     * @param array $extraWith
     * @return \XF\Entity\ProfilePost
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableProfilePost($profilePostId, array $extraWith = [])
    {
        $extraWith[] = 'User';
        $extraWith[] = 'ProfileUser';
        $extraWith[] = 'ProfileUser.Privacy';
        array_unique($extraWith);

        /** @var \XF\Entity\ProfilePost|null $profilePost */
        $profilePost = $this->em()->find('XF:ProfilePost', $profilePostId, $extraWith);
        if (!$profilePost) {
            throw $this->exception($this->notFound(\XF::phrase('requested_profile_post_not_found')));
        }
        if (!$profilePost->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $profilePost;
    }
}
