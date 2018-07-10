<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\Forum;
use XF\Mvc\ParameterBag;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class Thread extends AbstractController
{
    protected $orderChoices = [
        \Xfrocks\Api\XF\Transform\Thread::KEY_CREATE_DATE => ['post_date', 'asc'],
        \Xfrocks\Api\XF\Transform\Thread::KEY_UPDATE_DATE => ['last_post_date', 'asc', '_whereOp'],
        \Xfrocks\Api\XF\Transform\Thread::KEY_VIEW_COUNT => ['view_count', 'asc']
    ];

    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->thread_id) {
            return $this->actionSingle($params->thread_id);
        }

        $params = $this
            ->params()
            ->define('forum_id', 'uint', 'forum id to filter')
            ->define('thread_ids', 'str', 'thread ids to filter (separated by comma)')
            ->define('creator_user_id', 'uint', 'creator user id to filter')
            ->define('sticky', 'bool', 'sticky to filter')
            ->define('thread_prefix_id', 'uint', 'thread prefix id to filter')
//            ->define('thread_tag_id', 'uint', 'thread tag id to filter')
            ->define(\Xfrocks\Api\XF\Transform\Thread::KEY_UPDATE_DATE, 'uint', 'timestamp to filter')
            ->defineOrder($this->orderChoices)
            ->definePageNav();

        if (!empty($params['thread_ids'])) {
            $threadIds = $params->filterCommaSeparatedIds('thread_ids');

            return $this->actionMultiple($threadIds);
        }

        /** @var \XF\Finder\Thread $finder */
        $finder = $this->finder('XF:Thread');

        $this->applyFilters($finder, $params);

        $total = $finder->total();
        /** @var \XF\Entity\Thread[] $threads */
        $threads = $total > 0 ? $finder->fetch() : [];

        $data = [
            'threads' => $this->transformEntitiesLazily($threads),
            'threads_total' => $total
        ];

        if ($params['forum_id'] > 0) {
            $forum = $this->assertViewableEntity('XF:Forum', $params['forum_id']);
            $this->transformEntityIfNeeded($data, 'forum', $forum);
        }

        PageNav::addLinksToData($data, $params, $total, 'threads');

        return $this->api($data);
    }

    public function actionPostIndex()
    {
        throw new \LogicException('Not implemented!');
    }

    public function actionPostAttachments()
    {
        throw new \LogicException('Not implemented!');
    }

    public function actionDeleteAttachments()
    {
        throw new \LogicException('Not implemented!');
    }

    public function actionGetFollowers(ParameterBag $params)
    {
        throw new \LogicException('Not implemented!');
    }

    public function actionPostFollowers(ParameterBag $params)
    {
        throw new \LogicException('Not implemented!');
    }

    public function actionDeleteFollowers(ParameterBag $params)
    {
        throw new \LogicException('Not implemented!');
    }

    public function actionMultiple(array $ids)
    {
        $threads = [];
        if (count($ids) > 0) {
            $threads = $this->finder('XF:Thread')
                ->whereIds($ids)
                ->fetch()
                ->filterViewable()
                ->sortByList($ids);
        }

        $data = [
            'threads' => $this->transformEntitiesLazily($threads)
        ];

        return $this->api($data);
    }

    public function actionSingle($threadId)
    {
        $thread = $this->assertViewableThread($threadId);

        $data = [
            'thread' => $this->transformEntityLazily($thread)
        ];

        return $this->api($data);
    }

    protected function applyFilters(\XF\Finder\Thread $finder, Params $params)
    {
        $params->limitFinderByPage($finder);

        if ($params['forum_id'] > 0) {
            /** @var Forum $forum */
            $forum = $this->assertViewableEntity('XF:Forum', $params['forum_id']);
            $finder->applyVisibilityChecksInForum($forum);
        }

        if (isset($this->orderChoices[$params['order']])) {
            $orderChoice = $this->orderChoices[$params['order']];
            $finder->order($orderChoice[0], $orderChoice[1]);

            switch ($orderChoice[0]) {
                case \Xfrocks\Api\XF\Transform\Thread::KEY_UPDATE_DATE:
                    $keyUpdateDate = \Xfrocks\Api\XF\Transform\Thread::KEY_UPDATE_DATE;
                    if ($params[$keyUpdateDate] > 0) {
                        $finder->where($orderChoice[0], $orderChoice['_whereOp'], $params[$keyUpdateDate]);
                    }

                    break;
            }
        }

        if ($params['creator_user_id'] > 0) {
            $finder->where('user_id', $params['creator_user_id']);
        }

        if ($this->request()->exists('sticky')) {
            $finder->where('sticky', $params['sticky']);
        }

        if ($params['thread_prefix_id'] > 0) {
            $finder->where('prefix_id', $params['thread_prefix_id']);
        }

        // TODO: Add more filters?
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
        $thread = $this->assertViewableEntity('XF:Thread', $threadId, $extraWith);

        return $thread;
    }
}
