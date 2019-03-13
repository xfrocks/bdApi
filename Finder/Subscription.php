<?php

namespace Xfrocks\Api\Finder;

use XF\Mvc\Entity\Finder;
use Xfrocks\Api\Admin\Controller\Entity;

class Subscription extends Finder
{
    /**
     * @return Subscription
     */
    public function active()
    {
        $this->whereOr(
            ['expire_date', 0],
            ['expire_date', '>', \XF::$time]
        );

        return $this;
    }

    /**
     * @param Entity $controller
     * @param array $filters
     * @return array
     */
    public function entityDoListData($controller, array $filters)
    {
        $this->with(['Client']);
        $this->setDefaultOrder('subscription_id', 'desc');

        return $filters;
    }

    /**
     * @param string $match
     * @param bool $prefixMatch
     * @return Subscription
     */
    public function entityDoXfFilter($match, $prefixMatch = false)
    {
        if (strlen($match) > 0) {
            $this->where(
                $this->columnUtf8('topic'),
                'LIKE',
                $this->escapeLike($match, $prefixMatch ? '?%' : '%?%')
            );
        }

        return $this;
    }
}
