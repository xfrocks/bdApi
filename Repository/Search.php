<?php

namespace Xfrocks\Api\Repository;

use XF\Entity\Forum;
use XF\Http\Request;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;
use XF\Repository\Node;
use Xfrocks\Api\Data\BatchJob;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Mvc\Reply\Api;
use Xfrocks\Api\Util\LazyTransformer;

class Search extends Repository
{
    const OPTION_SEARCH_TYPE = 'searchType';

    public function search(Params $input, $contentType = '', array $constraints = [], array $options = [])
    {
        $httpRequest = new Request($this->app()->inputFilterer(), $input->getFilteredValues());

        $searcher = $this->app()->search();
        $query = $searcher->getQuery();

        if (!empty($contentType)) {
            $typeHandler = $searcher->handler($contentType);
            $urlConstraints = [];

            $query->forTypeHandler($typeHandler, $httpRequest, $urlConstraints);
        }

        if (!empty($input['q'])) {
            $query->withKeywords($input['q']);
        }

        if (!empty($input['user_id'])) {
            $query->byUserId($input['user_id']);
        }

        if (!empty($options[self::OPTION_SEARCH_TYPE])) {
            $query->inType($options[self::OPTION_SEARCH_TYPE]);
        }

        if (!empty($input['forum_id'])) {
            /** @var Node $nodeRepo */
            $nodeRepo = $this->repository('XF:Node');
            /** @var Forum $forum */
            $forum = $this->em->find('XF:Forum', $input['forum_id']);
            $nodeIds = [];

            if ($forum) {
                $children = $nodeRepo->findChildren($forum->Node, false)->fetch();

                $nodeIds = $children->keys();
                $nodeIds[] = $forum->node_id;
            }


            $query->withMetadata('node', $nodeIds ?: $input['forum_id']);
        }

        if (!empty($input['thread_id'])) {
            $query->withMetadata('thread', $input['thread_id'])
                  ->inTitleOnly(false);
        }

        if ($query->getErrors()) {
            $errors = $query->getErrors();

            throw new PrintableException(reset($errors));
        }

        /** @var \XF\Repository\Search $xfSearchRepo */
        $xfSearchRepo = $this->repository('XF:Search');
        $search = $xfSearchRepo->runSearch($query, $constraints);

        return $search;
    }

    public function prepareResultsForApi(array $results)
    {
        $grouped = [];

        foreach ($results as $id => $result) {
            $grouped[$result[0]][$id] = $result[1];
        }

        $items = [];

        foreach ($grouped as $contentType => $contents) {
            $config = $this->getBatchJobConfig($contentType, array_values($contents));
            if (!$config) {
                continue;
            }

            $job = new BatchJob($this->app(), $config['method'], $config['params'], $config['uri']);
            $jobResult = null;

            try {
                $jobResult = $job->execute();
            } catch (\Exception $e) {
                if (\XF::$debugMode) {
                    \XF::logException($e);
                }
            }

            if (!($jobResult instanceof Api)) {
                continue;
            }

            $dataResults = $this->getDataResults($jobResult, $contentType, $contentKey);
            if (!$dataResults || !$contentKey) {
                continue;
            }

            foreach ($dataResults->toArray() as $item) {
                $items[$contentType . '-' . $item[$contentKey]] = $item;
            }
        }

        $data = [];
        foreach ($results as $resultKey => $result) {
            if (isset($items[$resultKey])) {
                $data[] = $items[$resultKey];
            }
        }

        return $data;
    }

    /**
     * @param Api $api
     * @param string $contentType
     * @param string $contentKey
     * @return LazyTransformer|null
     */
    public function getDataResults(Api $api, $contentType, &$contentKey = null)
    {
        switch ($contentType) {
            case 'thread':
                $contentKey = 'thread_id';

                return $api->getParam('threads');
            case 'post':
                $contentKey = 'post_id';

                return $api->getParam('posts');
            default:
                return null;
        }
    }

    public function getBatchJobConfig($contentType, array $ids)
    {
        $router = \XF::app()->router('api');

        switch ($contentType) {
            case 'thread':
                return [
                    'params' => [
                        'thread_ids' => implode(',', $ids)
                    ],
                    'uri' => $router->buildLink('threads'),
                    'method' => 'GET'
                ];
            case 'post':
                return [
                    'params' => [
                        'post_ids' => implode(',', $ids)
                    ],
                    'uri' => $router->buildLink('posts'),
                    'method' => 'GET'
                ];
            default:
                return null;
        }
    }
}