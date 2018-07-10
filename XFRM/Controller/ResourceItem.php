<?php

namespace Xfrocks\Api\XFRM\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Util\PageNav;
use Xfrocks\Api\Util\Tree;

class ResourceItem extends AbstractController
{
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

        if (!empty($params['resource_ids'])) {
            return $this->actionMultiple($params->filterCommaSeparatedIds('resource_ids'));
        }

        /** @var \XFRM\Finder\ResourceItem $finder */
        $finder = $this->finder('XFRM:ResourceItem');
        $finder->applyGlobalVisibilityChecks();
        $params->limitFinderByPage($finder);

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
            if ($params['in_sub']) {
                /** @var \XFRM\Repository\Category $categoryRepo */
                $categoryRepo = $this->repository('XFRM:Category');
                $categories = $this->finder('XFRM:Category')->fetch();
                $categoryTree = $categoryRepo->createCategoryTree($categories);
                $categoryIds = Tree::getAllChildIds($categoryTree, $categoryIds);
            }

            $categoryIds = array_unique($categoryIds);
            $finder->where('resource_category_id', $categoryIds);
            /** @var \XFRM\XF\Entity\User $user */
            $user = \XF::visitor();
            $user->cacheResourceCategoryPermissions($categoryIds);
        }

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

        $total = $finder->total();
        /** @var \XFRM\Entity\ResourceItem[] $resources */
        $resources = $total > 0 ? $finder->fetch() : [];

        $data = [
            'resources' => $this->transformEntitiesLazily($resources),
            'resources_total' => $total,
        ];

        $theCategory = null;
        if ($params['resource_category_id'] > 0) {
            /** @var \XFRM\Entity\Category $theCategory */
            $theCategory = $this->assertViewableEntity('XFRM:Category', $params['resource_category_id']);
        }
        if ($theCategory !== null) {
            $this->transformEntityIfNeeded($data, 'category', $theCategory);
        }

        PageNav::addLinksToData($data, $params, $total, 'resources');

        return $this->api($data);
    }

    protected function actionSingle($resourceId)
    {
        $resource = $this->assertViewableResource($resourceId);

        $data = [
            'resource' => $this->transformEntityLazily($resource)
        ];

        return $this->api($data);
    }

    protected function actionMultiple(array $ids)
    {
        $resources = [];
        if (count($ids) > 0) {
            $resources = $this->finder('XFRM:ResourceItem')
                ->whereIds($ids)
                ->fetch()
                ->filterViewable()
                ->sortByList($ids);
        }

        $data = [
            'resources' => $this->transformEntitiesLazily($resources)
        ];

        return $this->api($data);
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
        $resourceItem = $this->assertViewableEntity('XFRM:ResourceItem', $resourceId, $extraWith);
        return $resourceItem;
    }
}
