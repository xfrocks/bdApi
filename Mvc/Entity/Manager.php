<?php

namespace Xfrocks\Api\Mvc\Entity;

use Xfrocks\Api\Transformer;

class Manager extends \XF\Mvc\Entity\Manager
{
    public function getFinder($shortName)
    {
        $finder = parent::getFinder($shortName);

        /** @var Transformer $transformer */
        $transformer = \XF::app()->container('api.transformer');
        $handler = $transformer->handler($shortName);
        $with = $handler->getExtraWith();
        if (count($with) > 0) {
            $finder->with($with);
        }

        return $finder;
    }
}
