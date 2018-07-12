<?php

namespace Xfrocks\Api\Transform;

use Xfrocks\Api\Data\TransformContext;

interface AttachmentParent
{
    /**
     * @param TransformContext $context
     * @param string $key
     * @return mixed
     */
    public function attachmentCalculateDynamicValue($context, $key);

    /**
     * @param TransformContext $context
     * @param array $links
     * @return array|null
     */
    public function attachmentCollectLinks($context, array &$links);

    /**
     * @param TransformContext $context
     * @param array $permissions
     * @return array|null
     */
    public function attachmentCollectPermissions($context, array &$permissions);

    /**
     * @param TransformContext $context
     * @param array $mappings
     */
    public function attachmentGetMappings($context, array &$mappings);
}
