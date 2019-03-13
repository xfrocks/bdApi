<?php

namespace Xfrocks\Api\Finder;

use Xfrocks\Api\Admin\Controller\Entity;

class Client extends \XF\Mvc\Entity\Finder
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

    /**
     * @param string $match
     * @param bool $prefixMatch
     * @return Client
     */
    public function entityDoXfFilter($match, $prefixMatch = false)
    {
        if (strlen($match) > 0) {
            $this->where(
                $this->columnUtf8('name'),
                'LIKE',
                $this->escapeLike($match, $prefixMatch ? '?%' : '%?%')
            );
        }

        return $this;
    }
}
