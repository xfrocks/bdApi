<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\Forum;
use XF\Entity\Poll;
use XF\Entity\PollResponse;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Repository\Node;
use XF\Repository\ThreadWatch;
use XF\Service\Thread\Creator;
use XF\Service\Thread\Deleter;
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
            ->define('post_body', 'str', 'content of the new thread')
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

    public function actionPutIndex(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        return $this->rerouteController('Xfrocks\Api\Controller\Post', 'put-index', [
            'post_id' => $thread->first_post_id
        ]);
    }

    public function actionDeleteIndex(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        $params = $this
            ->params()
            ->define('reason', 'str', 'reason of the thread removal');

        if (!$thread->canDelete('soft', $error)) {
            return $this->noPermission($error);
        }

        /** @var Deleter $deleter */
        $deleter = $this->service('XF:Thread\Deleter', $thread);
        $deleter->delete('soft', $params['reason']);

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionPostAttachments()
    {
        $params = $this
            ->params()
            ->define('forum_id', 'uint', 'id of the container forum of the target thread')
            ->defineAttachmentHash()
            ->defineFile('file', 'binary data of the attachment');

        $forum = $this->assertViewableForum($params['forum_id']);

        $context = [
            'forum_id' => $forum->node_id
        ];

        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $tempHash = $attachmentPlugin->getAttachmentTempHash($context);

        return $attachmentPlugin->doUpload($tempHash, 'post', $context);
    }

    public function actionGetFollowers(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        $users = [];
        if ($thread->canWatch()) {
            $visitor = \XF::visitor();
            /** @var \XF\Entity\ThreadWatch|null $watch */
            $watch = $thread->Watch[$visitor->user_id];
            if ($watch) {
                $users[] = [
                    'user_id' => $visitor->user_id,
                    'username' => $visitor->username,
                    'follow' => [
                        'alert' => true,
                        'email' => $watch->email_subscribe
                    ]
                ];
            }
        }

        $data = [
            'users' => $users
        ];

        return $this->api($data);
    }

    public function actionPostFollowers(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        $params = $this
            ->params()
            ->define('email', 'bool', 'whether to receive notification as email');

        if (!$thread->canWatch($error)) {
            return $this->noPermission($error);
        }

        /** @var ThreadWatch $threadWatchRepo */
        $threadWatchRepo = $this->repository('XF:ThreadWatch');
        $threadWatchRepo->setWatchState(
            $thread,
            \XF::visitor(),
            $params['email'] ? 'watch_email' : 'watch_no_email'
        );

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionDeleteFollowers(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        /** @var ThreadWatch $threadWatchRepo */
        $threadWatchRepo = $this->repository('XF:ThreadWatch');
        $threadWatchRepo->setWatchState($thread, \XF::visitor(), '');

        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionGetFollowed()
    {
        $this->assertRegistrationRequired();

        $params = $this
            ->params()
            ->define('total', 'uint')
            ->definePageNav();

        /** @var \XF\Repository\Thread $threadRepo */
        $threadRepo = $this->repository('XF:Thread');
        $threadFinder = $threadRepo->findThreadsForWatchedList();

        if ($params['total'] > 0) {
            $data = [
                'threads_total' => $threadFinder->total()
            ];

            return $this->api($data);
        }

        $params->limitFinderByPage($threadFinder);

        $context = $this->params()->getTransformContext();
        $context->onTransformedCallbacks[] = function ($context, &$data) {
            $source = $context->getSource();
            if (!($source instanceof \XF\Entity\Thread)) {
                return;
            }

            $data['follow'] = [
                'alert' => true,
                'email' => $source->Watch[\XF::visitor()->user_id]->email_subscribe
            ];
        };

        $total = $threadFinder->total();
        $threads = $total > 0 ? $this->transformFinderLazily($threadFinder) : [];

        $data = [
            'threads' => $threads,
            'threads_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'threads/followed');

        return $this->api($data);
    }

    public function actionPostPollVotes(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        /** @var Poll|null $poll */
        $poll = $thread->Poll;
        if (!$poll) {
            return $this->noPermission();
        }

        if (!$poll->canVote($error)) {
            return $this->noPermission();
        }

        $params = $this
            ->params()
            ->define('response_id', 'uint', 'the id of the response to vote for')
            ->define('response_ids', 'array-uint', 'an array of ids of responses');

        /** @var \XF\Service\Poll\Voter $voter */
        $voter = $this->service('XF:Poll\Voter', $poll);

        $responseIds = $params['response_ids'];
        if ($params['response_id'] > 0) {
            $responseIds[] = $params['response_id'];
        }

        $voter->setVotes($responseIds);
        if (!$voter->validate($errors)) {
            return $this->error($errors);
        }

        $voter->save();
        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionGetPollResults(ParameterBag $params)
    {
        $thread = $this->assertViewableThread($params->thread_id);

        /** @var Poll|null $poll */
        $poll = $thread->Poll;
        if (!$poll) {
            return $this->noPermission();
        }

        if (!$poll->canViewResults($error)) {
            return $this->noPermission($error);
        }

        $userIds = [];
        foreach ($poll->Responses as $pollResponse) {
            $userIds = array_merge($userIds, array_keys($pollResponse->voters));
        }

        $users = $this->em()->findByIds('XF:User', $userIds);

        $transformContext = $this->params()->getTransformContext();
        $transformContext->onTransformedCallbacks[] = function ($context, &$data) use ($users) {
            $source = $context->getSource();
            if (!($source instanceof PollResponse)) {
                return;
            }

            $data['voters'] = [];

            foreach (array_keys($source->voters) as $userId) {
                if (isset($users[$userId])) {
                    /** @var \XF\Entity\User $user */
                    $user = $users[$userId];
                    $data['voters'][] = [
                        'user_id' => $user->user_id,
                        'username' => $user->username
                    ];
                }
            }
        };

        $finder = $poll->getRelationFinder('Responses');
        $data = [
            'results' => $this->transformFinderLazily($finder)
        ];

        return $this->api($data);
    }

    public function actionGetNew()
    {
        $this->assertRegistrationRequired();

        $this
            ->params()
            ->define('forum_id', 'uint')
            ->define('data_limit', 'uint')
            ->definePageNav();

        /** @var \XF\Repository\Thread $threadRepo */
        $threadRepo = $this->repository('XF:Thread');
        $finder = $threadRepo->findThreadsWithUnreadPosts();

        return $this->getNewOrRecentResponse('threads_new', $finder);
    }

    public function actionGetRecent()
    {
        $this
            ->params()
            ->define('forum_id', 'uint')
            ->define('data_limit', 'uint')
            ->definePageNav();

        /** @var \XF\Repository\Thread $threadRepo */
        $threadRepo = $this->repository('XF:Thread');
        $finder = $threadRepo->findThreadsWithUnreadPosts();

        return $this->getNewOrRecentResponse('threads_recent', $finder);
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

    protected function getNewOrRecentResponse($searchType, Finder $finder)
    {
        $params = $this->params();

        if ($params['forum_id'] > 0) {
            $forum = $this->assertViewableForum($params['forum_id']);

            /** @var Node $nodeRepo */
            $nodeRepo = $this->repository('XF:Node');
            $childNodes = $nodeRepo->findChildren($forum->Node, false)->fetch();

            $nodeIds = $childNodes->keys();
            $nodeIds[] = $forum->node_id;

            $finder->where('node_id', $nodeIds);
        }

        list($limit, $page) = $params->filterLimitAndPage();

        $finder->limit($limit);
        $threads = $finder->fetch();

        $searchResults = [];
        /** @var \XF\Entity\Thread $thread */
        foreach ($threads as $thread) {
            if ($thread->canView() && !$thread->isIgnored()) {
                $searchResults[] = ['thread', $thread->thread_id];
            }
        }

        $totalResults = count($searchResults);
        $search = null;
        $results = [];

        if ($totalResults > 0) {
            /** @var \XF\Entity\Search $search */
            $search = $this->em()->create('XF:Search');

            $search->search_type = $searchType;
            $search->search_results = $searchResults;
            $search->result_count = $totalResults;
            $search->search_order = 'date';
            $search->user_id = 0;

            $search->query_hash = md5(serialize($search->getNewValues()));

            $search->save();

            $searcher = $this->app()->search();
            $resultSet = $searcher->getResultSet($search->search_results);

            $resultSet->sliceResultsToPage(1, $limit, false);

            foreach ($resultSet->getResults() as $result) {
                /** @var \XF\Entity\Thread $thread */
                $thread = $threads[$result[1]];
                $results[] = $this->transformEntityLazily($thread);
            }
        }

        $data = [
            'results' => $results
        ];

        PageNav::addLinksToData($data, $params, $totalResults, 'search/results', $search);

        return $this->api($data);
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
