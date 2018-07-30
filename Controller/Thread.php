<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\Forum;
use XF\Mvc\ParameterBag;
use XF\Service\Thread\Creator;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class Thread extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->thread_id) {
            return $this->actionSingle($params->thread_id);
        }

        $params = $this->params()
            ->define('forum_id', 'uint', 'forum id to filter')
            ->define('creator_user_id', 'uint', 'creator user id to filter')
            ->define('sticky', 'bool', 'sticky to filter')
            ->define('thread_prefix_id', 'uint', 'thread prefix id to filter')
//            ->define('thread_tag_id', 'uint', 'thread tag id to filter')
            ->defineOrder([
                'thread_create_date' => ['post_date', 'asc'],
                'thread_create_date_reverse' => ['post_date', 'desc'],
                'thread_update_date' => ['last_post_date', 'asc', '_whereOp' => '>'],
                'thread_update_date_reverse' => ['last_post_date', 'desc', '_whereOp' => '<'],
                'thread_view_count' => ['view_count', 'asc'],
                'thread_view_count_reverse' => ['view_count', 'asc'],
            ])
            ->definePageNav()
            ->define(\Xfrocks\Api\XF\Transform\Thread::KEY_UPDATE_DATE, 'uint', 'timestamp to filter')
            ->define('thread_ids', 'str', 'thread ids to fetch (ignoring all filters, separated by comma)');

        if (!empty($params['thread_ids'])) {
            $threadIds = $params->filterCommaSeparatedIds('thread_ids');

            return $this->actionMultiple($threadIds);
        }

        /** @var \XF\Finder\Thread $finder */
        $finder = $this->finder('XF:Thread');
        $this->applyFilters($finder, $params);

        $orderChoice = $params->sortFinder($finder);
        if (is_array($orderChoice)) {
            switch ($orderChoice[0]) {
                case 'last_post_date':
                    $keyUpdateDate = \Xfrocks\Api\XF\Transform\Thread::KEY_UPDATE_DATE;
                    if ($params[$keyUpdateDate] > 0) {
                        $finder->where($orderChoice[0], $orderChoice['_whereOp'], $params[$keyUpdateDate]);
                    }
                    break;
            }
        }

        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $threads = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'threads' => $threads,
            'threads_total' => $total
        ];

        if ($params['forum_id'] > 0) {
            $forum = $this->assertViewableForum($params['forum_id']);
            $this->transformEntityIfNeeded($data, 'forum', $forum);
        }

        PageNav::addLinksToData($data, $params, $total, 'threads');

        return $this->api($data);
    }

    public function actionPostIndex()
    {
        $params = $this
            ->params()
            ->define('forum_id', 'uint', 'id of the target forum')
            ->define('thread_title', 'str', 'title of the new thread')
            ->define('post_body', 'str', 'contnet of the new thread')
            ->define('thread_prefix_id', 'uint', 'id of a prefix for the new thread')
            ->define('thread_tags', 'str', 'thread tags for the new thread')
            ->define('fields', 'array', 'thread fields for the new thread');

        $forum = $this->assertViewableForum($params['forum_id']);
        if (!$forum->canCreateThread($error)) {
            return $this->error($error);
        }

        /** @var Creator $creator */
        $creator = $this->service('XF:Thread\Creator', $forum);

        $creator->setContent($params['thread_title'], $params['post_body']);
        if ($params['thread_prefix_id']) {
            $creator->setPrefix($params['thread_prefix_id']);
        }

        if ($params['thread_tags']) {
            $creator->setTags($params['thread_tags']);
        }

        if ($params['fields']) {
            $creator->setCustomFields($params['fields']);
        }

        $creator->checkForSpam();

        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $this->assertNotFlooding('post');
        $thread = $creator->save();

        /** @var \XF\Repository\Thread $threadRepo */
        $threadRepo = $this->repository('XF:Thread');
        $threadRepo->markThreadReadByVisitor($thread, $thread->post_date);

        return $this->actionSingle($thread->thread_id);
    }

    public function actionPostAttachments()
    {
        $params = $this
            ->params()
            ->define('forum_id', 'uint', 'id of the container forum of the target thread')
            ->define('attachment_hash', 'str', 'a unique hash value')
            ->defineFile('file', 'binary data of the attachment');

        $forum = $this->assertViewableForum($params['forum_id']);

        $context = [
            'forum_id' => $forum->node_id
        ];

        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $tempHash = $attachmentPlugin->getAttachmentTempHash($params, $context);

        return $attachmentPlugin->doUpload($params, 'file', $tempHash, 'post', $context);
    }

    public function actionMultiple(array $ids)
    {
        $threads = [];
        if (count($ids) > 0) {
            $finder = $this->finder('XF:Thread')->whereIds($ids);
            $threads = $this->transformFinderLazily($finder)->sortByList($ids);
        }

        return $this->api(['threads' => $threads]);
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

        if ($params['forum_id'] > 0) {
            /** @var Forum $forum */
            $forum = $this->assertViewableForum($params['forum_id']);
            $finder->applyVisibilityChecksInForum($forum);
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
     * @param int $forumId
     * @param array $extraWith
     * @return Forum
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableForum($forumId, array $extraWith = [])
    {
        /** @var \XF\Entity\Forum $forum */
        $forum = $this->assertRecordExists('XF:Forum', $forumId, $extraWith, 'requested_forum_not_found');

        if (!$forum->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $forum;
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
}
