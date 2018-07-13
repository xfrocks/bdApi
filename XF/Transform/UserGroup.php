<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class UserGroup extends AbstractHandler
{
    const KEY_ID = 'user_group_id';
    const KEY_TITLE = 'user_group_title';

    public function getMappings($context)
    {
        return [
            'user_group_id' => self::KEY_ID,
            'title' => self::KEY_TITLE
        ];
    }
}
