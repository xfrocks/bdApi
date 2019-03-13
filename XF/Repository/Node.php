<?php

namespace Xfrocks\Api\XF\Repository;

use Xfrocks\Api\Transform\TransformContext;

class Node extends XFCP_Node
{
    /**
     * @param TransformContext $context
     * @param string $key
     * @return mixed
     */
    public function apiTransformCalculateDynamicValue(TransformContext $context, $key)
    {
        return null;
    }

    /**
     * @param TransformContext $context
     * @param array $links
     * @return void
     */
    public function apiTransformCollectLinks(TransformContext $context, array &$links)
    {
    }

    /**
     * @param TransformContext $context
     * @param array $permissions
     * @return void
     */
    public function apiTransformCollectPermissions(TransformContext $context, array &$permissions)
    {
    }

    /**
     * @param TransformContext $context
     * @param array $mappings
     * @return void
     */
    public function apiTransformGetMappings(TransformContext $context, array &$mappings)
    {
    }
}
