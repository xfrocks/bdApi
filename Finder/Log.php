<?php

namespace Xfrocks\Api\Finder;

use XF\Mvc\Entity\Finder;
use Xfrocks\Api\Admin\Controller\Entity;

class Log extends Finder
{
    /**
     * @param Entity $controller
     * @param array $filters
     * @return array
     */
    public function entityDoListData($controller, array $filters)
    {
        $this->setDefaultOrder('request_date', 'DESC');

        return $filters;
    }
}
