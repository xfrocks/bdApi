<?php

namespace Xfrocks\Api\XF\Repository;

use Xfrocks\Api\Transform\TransformContext;

class Node extends XFCP_Node
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

    public function apiTransformCalculateDynamicValue(TransformContext $context, $key)
    {
        return null;
    }
}
