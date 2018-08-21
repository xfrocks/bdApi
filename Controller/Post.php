<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use XF\Service\Thread\Replier;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class Post extends AbstractController
{
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

        if (!empty($params['post_ids'])) {
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

    public function actionMultiple(array $ids)
    {
        $posts = [];
        if (count($ids) > 0) {
            $finder = $this->finder('XF:Post')->whereIds($ids);
            $posts = $this->transformFinderLazily($finder)->sortByList($ids);
        }

        return $this->api(['posts' => $posts]);
    }

    public function actionSingle($postId)
    {
        $post = $this->assertViewablePost($postId);

        $data = [
            'post' => $this->transformEntityLazily($post)
        ];

        return $this->api($data);
    }

    public function actionPostIndex()
    {
        $params = $this
            ->params()
            ->define('thread_id', 'uint', 'id of the target thread')
            ->define('quote_post_id', 'uint', 'id of the quote post')
            ->define('post_body', 'content of the new post')
            ->defineAttachmentHash();

        if (!empty($params['quote_post_id'])) {
            $post = $this->assertViewablePost($params['quote_post_id']);
            if ($params['thread_id'] > 0 && $post->thread_id !== $params['thread_id']) {
                return $this->noPermission();
            }

            $thread = $post->Thread;
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

        $data = [
            'post' => $this->transformEntityLazily($post)
        ];

        return $this->api($data);
    }

    public function actionPostAttachments()
    {
        $params = $this
            ->params()
            ->defineFile('file', 'binary data of the attachment')
            ->define('thread_id', 'uint', 'id of the container thread of the target post')
            ->define('post_id', 'uint', 'id of the target post')
            ->defineAttachmentHash();

        if (empty($params['post_id']) && empty($params['thread_id'])) {
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

    protected function applyFilters(\XF\Finder\Post $finder, Params $params)
    {
        if ($params['thread_id'] > 0) {
            /** @var \XF\Entity\Thread $thread */
            $thread = $this->assertViewableThread($params['thread_id']);
            $finder->inThread($thread);
        }
    }
}
