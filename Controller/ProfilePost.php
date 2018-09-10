<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\LikedContent;
use XF\Mvc\ParameterBag;

class ProfilePost extends AbstractController
{
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

    public function actionGetLikes(ParameterBag $paramBag)
    {
        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        $finder = $profilePost->getRelationFinder('Likes');
        $finder->with('Liker');

        $users = [];

        /** @var LikedContent $liked */
        foreach ($finder->fetch() as $liked) {
            $users[] = [
                'user_id' => $liked->Liker->user_id,
                'username' => $liked->Liker->username
            ];
        }

        $data = ['users' => $users];
        return $this->api($data);
    }

    public function actionPostLikes(ParameterBag $paramBag)
    {
        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        if (!$profilePost->canLike($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (empty($profilePost->Likes[$visitor->user_id])) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('profile_post', $profilePost->profile_post_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionDeleteLikes(ParameterBag $paramBag)
    {
        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);

        if (!$profilePost->canLike($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (!empty($profilePost->Likes[$visitor->user_id])) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('profile_post', $profilePost->profile_post_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionGetComments(ParameterBag $paramBag)
    {
        $params = $this
            ->params()
            ->define('before', 'uint')
            ->define('page_of_comment_id', 'uint')
            ->define('comment_id', 'uint');

        $profilePost = $this->assertViewableProfilePost($paramBag->profile_post_id);


    }

    public function actionMultiple(array $ids)
    {
        $profilePosts = [];
        if (count($ids) > 0) {
            $profilePosts = $this->findAndTransformLazily('XF:ProfilePost', $ids);
        }

        return $this->api(['profile_posts' => $profilePosts]);
    }

    public function actionSingle($profilePostId)
    {
        $profilePost = $this->assertViewableProfilePost($profilePostId);

        $data = [
            'profile_post' => $this->transformEntityLazily($profilePost)
        ];

        return $this->api($data);
    }

    /**
     * @param $profilePostId
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

        /** @var \XF\Entity\ProfilePost $profilePost */
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