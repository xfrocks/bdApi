<?php

namespace Xfrocks\Api\Repository;

use XF\Mvc\Entity\Repository;
use Xfrocks\Api\Transform\TransformContext;

class Node extends Repository
{
    public function apiTransformGetMappings(TransformContext $context, array &$mappings)
    {
    }

    public function apiTransformCollectLinks(TransformContext $context, array &$links)
    {
    }

    public function apiTransformCollectPermissions(TransformContext $context, array &$permissions)
    {
    }
}
