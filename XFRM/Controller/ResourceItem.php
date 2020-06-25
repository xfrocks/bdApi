<?php

namespace Xfrocks\Api\XFRM\Controller;

use XF\Mvc\ParameterBag;
use XFRM\Entity\ResourceWatch;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Api\Util\PageNav;
use Xfrocks\Api\Util\Tree;

class ResourceItem extends AbstractController
{
    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->resource_id) {
            return $this->actionSingle($params->resource_id);
        }

        $params = $this->params()
            ->define('resource_category_id', 'uint', 'category id to filter')
            ->define('resource_category_ids', 'str', 'category ids to filter (separated by comma)')
            ->define('in_sub', 'bool', 'flag to include sub categories in filtering')
            ->defineOrder([
                'resource_create_date' => ['resource_date', 'asc'],
                'resource_create_date_reverse' => ['resource_date', 'desc'],
                'resource_update_date' => ['last_update', 'asc', '_whereOp' => '>'],
                'resource_update_date_reverse' => ['last_update', 'desc', '_whereOp' => '<'],
                'resource_download_count' => ['download_count', 'asc'],
                'resource_download_count_reverse' => ['download_count', 'desc'],
                'resource_rating_weighted' => ['rating_weighted', 'asc'],
                'resource_rating_weighted_reverse' => ['rating_weighted', 'desc'],
            ])
            ->definePageNav()
            ->define(\Xfrocks\Api\XFRM\Transform\ResourceItem::KEY_UPDATE_DATE, 'uint', 'timestamp to filter')
            ->define('resource_ids', 'str', 'resource ids to fetch (ignoring all filters, separated by comma)');

        if ($params['resource_ids'] !== '') {
            return $this->actionMultiple($params->filterCommaSeparatedIds('resource_ids'));
        }

        /** @var \XFRM\Finder\ResourceItem $finder */
        $finder = $this->finder('XFRM:ResourceItem');
        $this->applyFilters($finder, $params);

        $orderChoice = $params->sortFinder($finder);
        if (is_array($orderChoice)) {
            switch ($orderChoice[0]) {
                case 'last_update':
                    $keyUpdateDate = \Xfrocks\Api\XFRM\Transform\ResourceItem::KEY_UPDATE_DATE;
                    if ($params[$keyUpdateDate] > 0) {
                        $finder->where($orderChoice[0], $orderChoice['_whereOp'], $params[$keyUpdateDate]);
                    }
                    break;
            }
        }

        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $resources = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'resources' => $resources,
            'resources_total' => $total,
        ];

        $theCategory = null;
        if ($params['resource_category_id'] > 0) {
            /** @var \XFRM\Entity\Category $theCategory */
            $theCategory = $this->assertRecordExists('XFRM:Category', $params['resource_category_id']);
        }
        if ($theCategory !== null) {
            $this->transformEntityIfNeeded($data, 'category', $theCategory);
        }

        PageNav::addLinksToData($data, $params, $total, 'resources');

        return $this->api($data);
    }

    /**
     * @param array $ids
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
    public function actionMultiple(array $ids)
    {
        $resources = [];
        if (count($ids) > 0) {
            $resources = $this->findAndTransformLazily('XFRM:ResourceItem', $ids);
        }

        return $this->api(['resources' => $resources]);
    }


    /**
     * @param int $resourceId
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
    public function actionSingle($resourceId)
    {
        return $this->api([
            'resource' => $this->findAndTransformLazily(
                'XFRM:ResourceItem',
                intval($resourceId),
                'xfrm_requested_resource_not_found'
            )
        ]);
    }

    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetFollowers(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params->resource_id);

        $users = [];
        if ($resource->canWatch()) {
            $visitor = \XF::visitor();
            /** @var ResourceWatch|null $watch */
            $watch = $resource->Watch[$visitor->user_id];
            if ($watch !== null) {
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

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostFollowers(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params->resource_id);

        $params = $this
            ->params()
            ->define('email', 'bool', 'whether to receive notification as email');

        if (!$resource->canWatch($error)) {
            return $this->noPermission($error);
        }

        /** @var \XFRM\Repository\ResourceWatch $resourceWatchRepo */
        $resourceWatchRepo = $this->repository('XFRM:ResourceWatch');
        $resourceWatchRepo->setWatchState(
            $resource,
            \XF::visitor(),
            'watch',
            ['email_subscribe' => $params['email']]
        );

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteFollowers(ParameterBag $params)
    {
        $resource = $this->assertViewableResource($params->resource_id);

        /** @var \XFRM\Repository\ResourceWatch $resourceWatchRepo */
        $resourceWatchRepo = $this->repository('XFRM:ResourceWatch');
        $resourceWatchRepo->setWatchState($resource, \XF::visitor(), 'delete');

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetFollowed()
    {
        $params = $this
            ->params()
            ->define('total', 'uint')
            ->definePageNav();

        $this->assertRegistrationRequired();

        $visitor = \XF::visitor();
        $finder = $this->finder('XFRM:ResourceWatch')
            ->where('user_id', $visitor->user_id);

        if ($params['total'] > 0) {
            $data = [
                'resources_total' => $finder->total()
            ];

            return $this->api($data);
        }

        $finder->with('Resource');
        $params->limitFinderByPage($finder);

        /** @var ResourceWatch[] $resourceWatches */
        $resourceWatches = $finder->fetch();


        $context = $this->params()->getTransformContext();
        $context->onTransformedCallbacks[] = function ($context, &$data) use ($resourceWatches) {
            /** @var TransformContext $context */
            $source = $context->getSource();
            if (!($source instanceof \XFRM\Entity\ResourceItem)) {
                return;
            }

            /** @var ResourceWatch|null $watched */
            $watched = null;
            foreach ($resourceWatches as $resourceWatch) {
                if ($resourceWatch->resource_id == $source->resource_id) {
                    $watched = $resourceWatch;
                    break;
                }
            }

            if ($watched !== null) {
                $data['follow'] = [
                    'alert' => true,
                    'email' => $watched->email_subscribe
                ];
            }
        };

        $resources = [];
        foreach ($resourceWatches as $resourceWatch) {
            $resource = $resourceWatch->Resource;
            if ($resource !== null) {
                $resources[] = $this->transformEntityLazily($resource);
            }
        }

        $total = $finder->total();

        $data = [
            'resources' => $resources,
            'resources_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'resources/followed');

        return $this->api($data);
    }

    /**
     * @param \XFRM\Finder\ResourceItem $finder
     * @param Params $params
     * @return void
     */
    protected function applyFilters($finder, Params $params)
    {
        $finder->applyGlobalVisibilityChecks();

        $categoryIds = [];
        if ($params['resource_category_id'] > 0) {
            $categoryIds[] = $params['resource_category_id'];
        } else {
            $categoryIds = $params->filterCommaSeparatedIds('resource_category_ids');
            if (count($categoryIds) > 0) {
                sort($categoryIds);
            }
        }
        if (count($categoryIds) > 0) {
            if ($params['in_sub'] === true) {
                /** @var \XFRM\Repository\Category $categoryRepo */
                $categoryRepo = $this->repository('XFRM:Category');
                $categories = $this->finder('XFRM:Category')->fetch();
                $categoryTree = $categoryRepo->createCategoryTree($categories);
                $categoryIds = Tree::getAllChildIds($categoryTree, $categoryIds);
            }

            $categoryIds = array_unique($categoryIds);
            $finder->where('resource_category_id', $categoryIds);
        }
    }

    /**
     * @param int $resourceId
     * @param array $extraWith
     * @return \XFRM\Entity\ResourceItem
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableResource($resourceId, array $extraWith = [])
    {
        /** @var \XFRM\Entity\ResourceItem $resourceItem */
        $resourceItem = $this->assertRecordExists(
            'XFRM:ResourceItem',
            $resourceId,
            $extraWith,
            'xfrm_requested_resource_not_found'
        );

        if (!$resourceItem->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $resourceItem;
    }
}
