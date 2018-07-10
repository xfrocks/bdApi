<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class Post extends AbstractController
{
    protected $orderChoices = [
        'natural' => ['post_date', 'asc'],
        'natural_reverse' => ['post_date', 'desc'],
        'post_create_date' => ['post_date', 'asc'],
        'post_create_date_reverse' => ['post_date', 'desc']
    ];

    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->post_id) {
            return $this->actionSingle($params->post_id);
        }

        $params = $this
            ->params()
            ->define('thread_id', 'uint', 'thread id to filter')
            ->define('post_ids', 'str')
            ->definePageNav()
            ->defineOrder($this->orderChoices);

        if (!empty($params['post_ids'])) {
            $postIds = $params->filterCommaSeparatedIds('post_ids');

            return $this->actionMultiple($postIds);
        }

        /** @var \XF\Finder\Post $finder */
        $finder = $this->finder('XF:Post');

        $this->applyFilters($finder, $params);

        $total = $finder->total();
        /** @var \XF\Entity\Post[] $posts */
        $posts = $total > 0 ? $finder->fetch() : [];

        $data = [
            'posts' => $this->transformEntitiesLazily($posts),
            'posts_total' => $total
        ];

        if ($params['thread_id'] > 0) {
            $thread = $this->assertViewableEntity('XF:Thread', $params['thread_id']);
            $this->transformEntityIfNeeded($data, 'thread', $thread);
        }

        PageNav::addLinksToData($data, $params, $total, 'posts');

        return $this->api($data);
    }

    public function actionMultiple(array $ids)
    {
        $posts = [];
        if (count($ids) > 0) {
            $posts = $this->finder('XF:Post')
                ->whereIds($ids)
                ->fetch()
                ->filterViewable()
                ->sortByList($ids);
        }

        $data = [
            'posts' => $this->transformEntitiesLazily($posts)
        ];

        return $this->api($data);
    }

    public function actionSingle($postId)
    {
        $post = $this->assertViewablePost($postId);

        $data = [
            'post' => $this->transformEntityLazily($post)
        ];

        return $this->api($data);
    }

    public function actionPostAttachments()
    {
        throw new \LogicException('Not implemented!');
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
        $post = $this->assertViewableEntity('XF:Post', $postId, $extraWith);

        return $post;
    }

    protected function applyFilters(\XF\Finder\Post $finder, Params $params)
    {
        $params->limitFinderByPage($finder);

        if ($params['thread_id'] > 0) {
            /** @var \XF\Entity\Thread $thread */
            $thread = $this->assertViewableEntity('XF:Thread', $params['thread_id']);
            $finder->applyVisibilityChecksInThread($thread);
        }

        if (isset($this->orderChoices[$params['order']])) {
            $orderChoice = $this->orderChoices[$params['order']];

            $finder->order($orderChoice[0], $orderChoice[1]);
        }
    }
}
