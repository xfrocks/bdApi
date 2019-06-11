<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use XF\Service\Post\Deleter;
use XF\Service\Post\Editor;
use XF\Service\Thread\Replier;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class Post extends AbstractController
{
    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->post_id) {
            return $this->actionSingle($params->post_id);
        }

        $params = $this->params()
            ->define('thread_id', 'uint', 'thread id to filter')
            ->defineOrder([
                'natural' => ['post_date', 'asc'],
                'natural_reverse' => ['post_date', 'desc'],
                'post_create_date' => ['post_date', 'asc'],
                'post_create_date_reverse' => ['post_date', 'desc']
            ])
            ->definePageNav()
            ->define('post_ids', 'str', 'post ids to fetch (ignoring all filters, separated by comma)');

        if ($params['post_ids'] !== '') {
            $postIds = $params->filterCommaSeparatedIds('post_ids');

            return $this->actionMultiple($postIds);
        }

        /** @var \XF\Finder\Post $finder */
        $finder = $this->finder('XF:Post');
        $this->applyFilters($finder, $params);
        $params->sortFinder($finder);
        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $posts = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'posts' => $posts,
            'posts_total' => $total
        ];

        if ($params['thread_id'] > 0) {
            $thread = $this->assertViewableThread($params['thread_id']);
            $this->transformEntityIfNeeded($data, 'thread', $thread);
        }

        PageNav::addLinksToData($data, $params, $total, 'posts');

        return $this->api($data);
    }

    /**
     * @param array $ids
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
    public function actionMultiple(array $ids)
    {
        $posts = [];
        if (count($ids) > 0) {
            $posts = $this->findAndTransformLazily('XF:Post', $ids);
        }

        return $this->api(['posts' => $posts]);
    }

    /**
     * @param int $postId
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
    public function actionSingle($postId)
    {
        return $this->api([
            'post' => $this->findAndTransformLazily('XF:Post', intval($postId), 'requested_post_not_found')
        ]);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostIndex()
    {
        $params = $this
            ->params()
            ->define('thread_id', 'uint', 'id of the target thread')
            ->define('quote_post_id', 'uint', 'id of the quote post')
            ->define('post_body', 'str', 'content of the new post')
            ->defineAttachmentHash();

        if ($params['quote_post_id'] > 0) {
            $post = $this->assertViewablePost($params['quote_post_id']);
            if ($params['thread_id'] > 0 && $post->thread_id !== $params['thread_id']) {
                return $this->noPermission();
            }

            $thread = $post->Thread;
            if (!$thread) {
                return $this->notFound();
            }

            $postBody = $post->getQuoteWrapper($post->message) . $params['post_body'];
        } else {
            $thread = $this->assertViewableThread($params['thread_id']);

            $postBody = $params['post_body'];
        }

        if (!$thread->canReply($error)) {
            return $this->noPermission($error);
        }

        /** @var Replier $replier */
        $replier = $this->service('XF:Thread\Replier', $thread);

        $replier->setMessage($postBody);

        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $tempHash = $attachmentPlugin->getAttachmentTempHash([
            'thread_id' => $thread->thread_id
        ]);

        $replier->setAttachmentHash($tempHash);

        $replier->checkForSpam();

        if (!$replier->validate($errors)) {
            return $this->error($errors);
        }

        $post = $replier->save();

        return $this->actionSingle($post->post_id);
    }

    /**
     * @param ParameterBag $pb
     * @return mixed|\XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPutIndex(ParameterBag $pb)
    {
        $post = $this->assertViewablePost($pb->post_id);

        $params = $this
            ->params()
            ->define('post_body', 'str', 'new content of the post')
            ->define('thread_title', 'str', 'new title of the thread')
            ->define('thread_prefix_id', 'uint', 'new id of the thread\'s prefix')
            ->define('thread_tags', 'str', 'new tags of the thread')
            ->define('fields', 'array')
            ->defineAttachmentHash();

        if (!$post->canEdit($error)) {
            return $this->noPermission($error);
        }

        /** @var Editor $editor */
        $editor = $this->service('XF:Post\Editor', $post);

        $editor->setMessage($params['post_body']);

        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $attachmentContentData = $pb->_attachmentContentData ?: ['post_id' => $post->post_id];
        $tempHash = $attachmentPlugin->getAttachmentTempHash($attachmentContentData);

        $editor->setAttachmentHash($tempHash);

        $editor->checkForSpam();

        $threadEditor = null;
        $tagger = null;
        $errors = [];

        if ($post->isFirstPost()) {
            /** @var \XF\Service\Thread\Editor $threadEditor */
            $threadEditor = $this->service('XF:Thread\Editor', $post->Thread);

            if ($this->request()->exists('thread_title')) {
                $threadEditor->setTitle($params['thread_title']);
            }

            $threadEditor->setPrefix($params['thread_prefix_id']);

            if ($this->request()->exists('fields')) {
                $threadEditor->setCustomFields($params['fields']);
            }

            if ($post->Thread->canEditTags()) {
                /** @var \XF\Service\Tag\Changer $tagger */
                $tagger = $this->service('XF:Tag\Changer', 'thread', $post->Thread);

                $tagger->setEditableTags($params['thread_tags']);

                if ($tagger->hasErrors()) {
                    $errors = array_merge($errors, $tagger->getErrors());
                }
            }

            $threadErrors = [];
            $threadEditor->validate($threadErrors);

            $errors = array_merge($errors, $threadErrors);
        }

        $postErrors = [];
        $editor->validate($postErrors);

        $errors = array_merge($errors, $postErrors);
        if (count($errors) > 0) {
            return $this->error($errors);
        }

        $post = $editor->save();

        if ($threadEditor) {
            $threadEditor->save();
        }

        if ($tagger) {
            $tagger->save();
        }

        if (is_callable($pb->_replyCallback)) {
            return call_user_func($pb->_replyCallback, $this);
        }

        return $this->actionSingle($post->post_id);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteIndex(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $params = $this
            ->params()
            ->define('reason', 'str', 'reason of the post removal');

        if (!$post->canDelete('soft', $error)) {
            return $this->noPermission($error);
        }

        /** @var Deleter $deleter */
        $deleter = $this->service('XF:Post\Deleter', $post);
        $deleter->delete('soft', $params['reason']);

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Reroute|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetAttachments(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $params = $this
            ->params()
            ->define('attachment_id', 'uint');

        if ($params['attachment_id'] > 0) {
            return $this->rerouteController('Xfrocks\Api\Controller\Attachment', 'get-data');
        }

        $finder = $post->getRelationFinder('Attachments');

        $data = [
            'attachments' => $this->transformFinderLazily($finder)
        ];

        return $this->api($data);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionPostAttachments()
    {
        $params = $this
            ->params()
            ->defineFile('file', 'binary data of the attachment')
            ->define('thread_id', 'uint', 'id of the container thread of the target post')
            ->define('post_id', 'uint', 'id of the target post')
            ->defineAttachmentHash();

        if ($params['post_id'] === '' && $params['thread_id'] === '') {
            return $this->error(\XF::phrase('bdapi_slash_posts_attachments_requires_ids'), 400);
        }

        $context = [
            'thread_id' => $params['thread_id'],
            'post_id' => $params['post_id']
        ];

        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $tempHash = $attachmentPlugin->getAttachmentTempHash($context);

        return $attachmentPlugin->doUpload($tempHash, 'post', $context);
    }

    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetLikes(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $finder = $post->getRelationFinder('Reactions');
        $finder->with('ReactionUser');

        $users = [];

        /** @var \XF\Entity\ReactionContent $reactionContent */
        foreach ($finder->fetch() as $reactionContent) {
            $user = $reactionContent->ReactionUser;

            $users[] = [
                'user_id' => $user->user_id,
                'username' => $user->username
            ];
        }

        $data = ['users' => $users];
        return $this->api($data);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostLikes(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        if (!$post->canReact($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (!$post->isReactedTo()) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('post', $post->post_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteLikes(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        if (!$post->canReact($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if ($post->isReactedTo()) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('post', $post->post_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostReport(ParameterBag $params)
    {
        $post = $this->assertViewablePost($params->post_id);

        $params = $this
            ->params()
            ->define('message', 'str', 'reason of the report');

        if (!$post->canReport($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Service\Report\Creator $creator */
        $creator = $this->service('XF:Report\Creator', 'post', $post);
        $creator->setMessage($params['message']);

        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $creator->save();

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param int $postId
     * @param array $extraWith
     * @return \XF\Entity\Post
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewablePost($postId, array $extraWith = [])
    {
        /** @var \XF\Entity\Post $post */
        $post = $this->assertRecordExists('XF:Post', $postId, $extraWith, 'requested_post_not_found');

        if (!$post->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $post;
    }

    /**
     * @param int $threadId
     * @param array $extraWith
     * @return \XF\Entity\Thread
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableThread($threadId, array $extraWith = [])
    {
        /** @var \XF\Entity\Thread $thread */
        $thread = $this->assertRecordExists('XF:Thread', $threadId, $extraWith, 'requested_thread_not_found');

        if (!$thread->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $thread;
    }

    /**
     * @param \XF\Finder\Post $finder
     * @param Params $params
     * @return void
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function applyFilters($finder, Params $params)
    {
        if ($params['thread_id'] > 0) {
            /** @var \XF\Entity\Thread $thread */
            $thread = $this->assertViewableThread($params['thread_id']);
            $finder->inThread($thread);
        }
    }
}
