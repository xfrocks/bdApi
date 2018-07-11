<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Data\BatchJob;
use Xfrocks\Api\Mvc\Reply\Api;
use Xfrocks\Api\Util\LazyTransformer;
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

    public function actionGetResults(ParameterBag $params)
    {
        /** @var \XF\Entity\Search $search */
        $search = $this->assertViewableEntity('XF:Search', $params->search_id);
        if ($search->user_id !== \XF::visitor()->user_id) {
            return $this->notFound();
        }

        $params = $this
            ->params()
            ->definePageNav();

        $page = $params['page'];
        $perPage = min($this->options()->searchResultsPerPage, $params['limit']);

        $searcher = $this->app()->search();
        $resultSet = $searcher->getResultSet($search->search_results);

        $resultSet->sliceResultsToPage($page, $perPage);

        if (!$resultSet->countResults()) {
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        $results = $this->searchRepo()->prepareResultsForApi($resultSet->getResults());

        $data = [
            'data_total' => $search->result_count,
            'data' => $results
        ];

        PageNav::addLinksToData($data, $params, $data['data_total'], 'search/results');

        return $this->api($data);
    }

    public function actionPostThreads()
    {
        if (!\XF::visitor()->canSearch($error)) {
            return $this->noPermission($error);
        }

        $params = $this
            ->params()
            ->define('q', 'str', 'query to search for')
            ->definePageNav()
            ->define('forum_id', 'uint', 'id of the container forum to search for contents')
            ->define('user_id', 'uint', 'id of the creator to search for contents');

        if (empty($params['q'])) {
            return $this->error(\XF::phrase('bdapi_slash_search_requires_q'), 400);
        }

        $search = $this->searchRepo()->search($params, 'thread');
        if (!$search) {
            // no results.
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        $paramBag = new ParameterBag();
        $paramBag->offsetSet('search_id', $search->search_id);

        return $this->rerouteController(__CLASS__, 'getResults', $paramBag);
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