<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class ThreadPrefix extends AbstractHandler
{
    const KEY_ID = 'prefix_id';
    const KEY_TITLE = 'prefix_title';

    public function getMappings()
    {
        return [
            'prefix_id' => self::KEY_ID,
            'title' => self::KEY_TITLE
        ];
    }
}
