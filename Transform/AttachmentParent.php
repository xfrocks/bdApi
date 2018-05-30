<?php

namespace Xfrocks\Api\Transform;

interface AttachmentParent
{
    public function attachmentCalculateDynamicValue($attachmentHandler, $key);

    public function attachmentCollectLinks($attachmentHandler, array &$links);

    public function attachmentCollectPermissions($attachmentHandler, array &$permissions);

    public function attachmentGetMappings($attachmentHandler, array &$mappings);
}
