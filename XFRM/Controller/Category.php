<?php

namespace Xfrocks\Api\XFRM\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Controller\AbstractController;

class Category extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->resource_category_id) {
            return $this->actionSingle($params->resource_category_id);
        }

        /** @var \XFRM\XF\Entity\User $user */
        $user = \XF::visitor();
        $user->cacheResourceCategoryPermissions();

        $finder = $this->finder('XFRM:Category')->order('lft');
        $categories = $this->transformFinderLazily($finder);

        return $this->api(['categories' => $categories]);
    }

    protected function actionSingle($resourceCategoryId)
    {
        $category = $this->assertViewableCategory($resourceCategoryId);

        $data = [
            'category' => $this->transformEntityLazily($category)
        ];

        return $this->api($data);
    }

    /**
     * @param int $resourceCategoryId
     * @param array $extraWith
     * @return \XFRM\Entity\Category
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableCategory($resourceCategoryId, array $extraWith = [])
    {
        /** @var \XFRM\Entity\Category $category */
        $category = $this->assertRecordExists(
            'XFRM:Category',
            $resourceCategoryId,
            $extraWith,
            'xfmg_requested_category_not_found'
        );

        if (!$category->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $category;
    }
}
