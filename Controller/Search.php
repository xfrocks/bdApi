<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Entity\Entity;
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

        list($perPage, $page) = $params->filterLimitAndPage();

        $searcher = $this->app()->search();
        $resultSet = $searcher->getResultSet($search->search_results);

        $resultSet->sliceResultsToPage($page, $perPage);

        if (!$resultSet->countResults()) {
            return $this->error(\XF::phrase('no_results_found'), 400);
        }

        $grouped = [];
        $results = $resultSet->getResults();

        foreach ($results as $id => $result) {
            $grouped[$result[0]][$id] = $result[1];
        }

        $data = [];
        $searcher = $this->app()->search();

        foreach ($grouped as $contentType => $contents) {
            $typeHandler = $searcher->handler(strval($contentType));
            $entities = $typeHandler->getContent(array_values($contents), true);

            /** @var Entity $entity */
            foreach ($entities as $entity) {
                $dataKey = $contentType . '-' . $entity->getEntityId();
                if (!isset($results[$dataKey])) {
                    continue;
                }

                $data[] = $this->transformEntityLazily($entity);
            }
        }

        $data = [
            'data_total' => $search->result_count,
            'data' => $data
        ];

        PageNav::addLinksToData($data, $params, $data['data_total'], 'search/results', $search);

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

        return $this->rerouteController(__CLASS__, 'getResults', ['search_id' => $search->search_id]);
    }

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
     * @return \Xfrocks\Api\Repository\Search
     */
    protected function searchRepo()
    {
        /** @var \Xfrocks\Api\Repository\Search $searchRepo */
        $searchRepo = $this->repository('Xfrocks\Api:Search');

        return $searchRepo;
    }
}
