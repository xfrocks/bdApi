<?php

namespace Xfrocks\Api\Transformer\XF;

use Xfrocks\Api\Transformer\AbstractHandler;

class UserGroup extends AbstractHandler
{
    const KEY_ID = 'user_group_id';
    const KEY_TITLE = 'user_group_title';

    public function getMappings()
    {
        return [
            'user_group_id' => self::KEY_ID,
            'title' => self::KEY_TITLE
        ];
    }
}
