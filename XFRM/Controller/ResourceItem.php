<?php

namespace Xfrocks\Api\XFRM\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Util\PageNav;
use Xfrocks\Api\Util\Tree;

class ResourceItem extends AbstractController
{
    protected $orderChoices = [
        'resource_create_date' => ['resource_date', 'asc'],
        'resource_create_date_reverse' => ['resource_date', 'desc'],
        'resource_update_date' => ['last_update', 'asc', '_whereOp' => '>'],
        'resource_update_date_reverse' => ['last_update', 'desc', '_whereOp' => '<'],
        'resource_download_count' => ['download_count', 'asc'],
        'resource_download_count_reverse' => ['download_count', 'desc'],
        'resource_rating_weighted' => ['rating_weighted', 'asc'],
        'resource_rating_weighted_reverse' => ['rating_weighted', 'desc'],
    ];

    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->resource_id) {
            return $this->actionSingle($params->resource_id);
        }

        $params = $this->params()
            ->define('resource_category_id', 'uint', 'category id to filter')
            ->define('resource_category_ids', 'str', 'category ids to filter (separated by comma)')
            ->define('in_sub', 'bool', 'flag to include sub categories in filtering')
            ->defineOrder($this->orderChoices)
            ->definePageNav()
            ->define(\Xfrocks\Api\XFRM\Transform\ResourceItem::KEY_UPDATE_DATE, 'uint', 'timestamp to filter')
            ->define('resource_ids', 'str', 'resource ids to fetch (ignoring all filters, separated by comma)');

        if (!empty($params['resource_ids'])) {
            return $this->actionMultiple($params->filterCommaSeparatedIds('resource_ids'));
        }

        $pageNavParams = [];
        /** @var \XFRM\Finder\ResourceItem $finder */
        $finder = $this->finder('XFRM:ResourceItem');
        $finder->applyGlobalVisibilityChecks();
        $params->limitFinderByPage($finder);

        $categoryIds = [];
        if ($params['resource_category_id'] > 0) {
            $categoryIds[] = $params['resource_category_id'];
            $pageNavParams['resource_category_id'] = $params['resource_category_id'];
        } else {
            $categoryIds = $params->filterCommaSeparatedIds('resource_category_ids');
            if (count($categoryIds) > 0) {
                sort($categoryIds);
                $pageNavParams['resource_category_ids'] = implode(',', $categoryIds);
            }
        }
        if (count($categoryIds) > 0) {
            if ($params['in_sub']) {
                /** @var \XFRM\Repository\Category $categoryRepo */
                $categoryRepo = $this->repository('XFRM:Category');
                $categories = $this->finder('XFRM:Category')->fetch();
                $categoryTree = $categoryRepo->createCategoryTree($categories);
                $categoryIdsCountBefore = count($categoryIds);
                $categoryIds = Tree::getAllChildIds($categoryTree, $categoryIds);
                $categoryIdsCountAfter = count($categoryIds);
                if ($categoryIdsCountAfter > $categoryIdsCountBefore) {
                    $pageNavParams['in_sub'] = 1;
                }
            }

            $categoryIds = array_unique($categoryIds);
            $finder->where('resource_category_id', $categoryIds);
            /** @var \XFRM\XF\Entity\User $user */
            $user = \XF::visitor();
            $user->cacheResourceCategoryPermissions($categoryIds);
        }

        if (isset($this->orderChoices[$params['order']])) {
            $orderChoice = $this->orderChoices[$params['order']];
            $finder->order($orderChoice[0], $orderChoice[1]);
            $pageNavParams['order'] = $params['order'];

            switch ($orderChoice[0]) {
                case 'last_update':
                    $keyUpdateDate = \Xfrocks\Api\XFRM\Transform\ResourceItem::KEY_UPDATE_DATE;
                    if ($params[$keyUpdateDate] > 0) {
                        $finder->where($orderChoice[0], $orderChoice['_whereOp'], $params[$keyUpdateDate]);
                        $pageNavParams[$keyUpdateDate] = $params[$keyUpdateDate];
                    }
                    break;
            }
        }

        $total = $finder->total();
        $resources = $total > 0 ? $finder->fetch() : [];

        $data = [
            'resources' => $this->transformEntitiesLazily($resources),
            'resources_total' => $total,
        ];

        $theCategory = null;
        if ($params['resource_category_id'] > 0) {
            /** @var \XFRM\Entity\Category $theCategory */
            $theCategory = $this->em()->find(
                'XFRM:Category',
                $params['resource_category_id'],
                $this->getFetchWith('XFRM:Category')
            );
            if (empty($theCategory) || !$theCategory->canView()) {
                return $this->noPermission();
            }
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
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->assertViewableEntity('XFRM:ResourceItem', $resourceId, $extraWith);
    }
}
