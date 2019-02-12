<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

class ThreadPrefix extends AbstractHandler
{
    const KEY_ID = 'prefix_id';
    const KEY_TITLE = 'prefix_title';

    public function canView(TransformContext $context)
    {
        return true;
    }

    public function getMappings(TransformContext $context)
    {
        return [
            'prefix_id' => self::KEY_ID,
            'title' => self::KEY_TITLE
        ];
    }
}
