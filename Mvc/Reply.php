<?php

namespace Xfrocks\Api\Mvc;

use XF\Mvc\Reply\AbstractReply;
use XF\Util\Arr;

class Reply extends AbstractReply
{
    protected $data = [];

    public function __construct(array $data = [])
    {
        $this->setData($data, false);
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $params, $merge = true)
    {
        if ($merge) {
            $this->data = Arr::mapMerge($this->data, $params);
        } else {
            $this->data = $params;
        }
    }
}
