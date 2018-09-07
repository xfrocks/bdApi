<?php

namespace Xfrocks\Api\Finder;

use XF\Mvc\Entity\Finder;

class Subscription extends Finder
{
    public function active()
    {
        $this->whereOr(
            ['expire_date', 0],
            ['expire_date', '>', \XF::$time]
        );

        return $this;
    }
}
