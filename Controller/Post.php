<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
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
