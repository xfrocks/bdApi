<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\TransformContext;

class UserGroup extends AbstractHandler
{
    const KEY_ID = 'user_group_id';
    const KEY_TITLE = 'user_group_title';

    public function canView(TransformContext $context)
    {
        return true;
    }

    public function getMappings(TransformContext $context)
    {
        return [
            'user_group_id' => self::KEY_ID,
            'title' => self::KEY_TITLE
        ];
    }
}
