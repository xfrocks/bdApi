<?php

namespace Xfrocks\Api\Finder;

use Xfrocks\Api\Admin\Controller\Entity;

class Token extends \XF\Mvc\Entity\Finder
{
    /**
     * @param Entity $controller
     * @param array $filters
     * @return array
     */
    public function entityDoListData($controller, array $filters)
    {
        $this->with('User');

        return $filters;
    }
}
