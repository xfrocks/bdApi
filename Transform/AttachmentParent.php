<?php

namespace Xfrocks\Api\Transform;

interface AttachmentParent
{
    /**
     * @param TransformContext $context
     * @param string $key
     * @return mixed
     */
    public function attachmentCalculateDynamicValue(TransformContext $context, $key);

    /**
     * @param TransformContext $context
     * @param array $links
     * @return void
     */
    public function attachmentCollectLinks(TransformContext $context, array &$links);

    /**
     * @param TransformContext $context
     * @param array $permissions
     * @return void
     */
    public function attachmentCollectPermissions(TransformContext $context, array &$permissions);

    /**
     * @param TransformContext $context
     * @param array $mappings
     * @return void
     */
    public function attachmentGetMappings(TransformContext $context, array &$mappings);
}
