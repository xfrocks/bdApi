<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Util\PageNav;

class Search extends AbstractController
{
    public function actionGetIndex()
    {
        $data = [
            'links' => [
                'posts' => $this->buildApiLink('search/posts'),
                'threads' => $this->buildApiLink('search/threads')
            ]
        ];

        return $this->api($data);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetResults(ParameterBag $params)
    {
        $search = $this->assertViewableSearch($params->search_id);

        $params = $this
            ->params()
            ->definePageNav();

        list($perPage, $page) = $params->filterLimitAndPage();

        $searcher = $this->app()->search();
        $resultSet = $searcher->getResultSet($search->search_results);

        $resultSet->sliceResultsToPage($page, $perPage, false);

        if (!$resultSet->countResults()) {
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        /** @var \Xfrocks\Api\ControllerPlugin\Search $searchPlugin */
        $searchPlugin = $this->plugin('Xfrocks\Api:Search');

        $data = [
            'data_total' => $search->result_count,
            'data' => $searchPlugin->prepareSearchResults($resultSet->getResults())
        ];

        PageNav::addLinksToData($data, $params, $data['data_total'], 'search/results', $search);

        return $this->api($data);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Reroute
     * @throws \XF\PrintableException
     */
    public function actionPostThreads()
    {
        if (!\XF::visitor()->canSearch($error)) {
            return $this->noPermission($error);
        }

        $params = $this
            ->params()
            ->define('q', 'str', 'query to search for')
            ->define('forum_id', 'uint', 'forum id to filter')
            ->define('user_id', 'uint', 'creator user id to filter');

        if (empty($params['q'])) {
            return $this->error(\XF::phrase('bdapi_slash_search_requires_q'), 400);
        }

        $search = $this->searchRepo()->search($params, 'thread');
        if (!$search) {
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        return $this->rerouteController(__CLASS__, 'getResults', ['search_id' => $search->search_id]);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Reroute
     * @throws \XF\PrintableException
     */
    public function actionPostPosts()
    {
        if (!\XF::visitor()->canSearch($error)) {
            return $this->noPermission($error);
        }

        $params = $this
            ->params()
            ->define('q', 'str', 'query to search for')
            ->define('forum_id', 'uint', 'id of the container forum to search for contents')
            ->define('thread_id', 'uint', 'id of the container thread to search for posts')
            ->define('user_id', 'uint', 'id of the creator to search for contents');

        if (empty($params['q'])) {
            return $this->error(\XF::phrase('bdapi_slash_search_requires_q'), 400);
        }

        $search = $this->searchRepo()->search($params, 'post');
        if (!$search) {
            // no results.
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        return $this->rerouteController(__CLASS__, 'getResults', ['search_id' => $search->search_id]);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message|\XF\Mvc\Reply\Reroute
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionUserTimeline(ParameterBag $params)
    {
        /** @var \XF\Entity\User $user */
        $user = $this->assertRecordExists('XF:User', $params->user_id, [], 'requested_user_not_found');

        $searcher = $this->app->search();
        $query = $searcher->getQuery();
        $query->byUserId($user->user_id)
            ->orderedBy('date');

        /** @var \XF\Repository\Search $searchRepo */
        $searchRepo = $this->repository('XF:Search');
        $search = $searchRepo->runSearch($query, [
            'users' => $user->username
        ], false);

        if (!$search) {
            return $this->message(\XF::phrase('no_results_found'));
        }

        return $this->rerouteController(__CLASS__, 'get-results', [
            'search_id' => $search->search_id
        ]);
    }

    /**
     * @param int $searchId
     * @param array $extraWith
     * @return \XF\Entity\Search
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableSearch($searchId, array $extraWith = [])
    {
        /** @var \XF\Entity\Search $search */
        $search = $this->assertRecordExists('XF:Search', $searchId, $extraWith, 'no_results_found');

        if ($search->user_id !== \XF::visitor()->user_id) {
            throw $this->exception($this->notFound(\XF::phrase('no_results_found')));
        }

        return $search;
    }

    /**
     * @return \Xfrocks\Api\Repository\Search
     */
    protected function searchRepo()
    {
        /** @var \Xfrocks\Api\Repository\Search $searchRepo */
        $searchRepo = $this->repository('Xfrocks\Api:Search');

        return $searchRepo;
    }
}
