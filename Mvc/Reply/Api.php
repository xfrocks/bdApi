<?php

namespace Xfrocks\Api\Mvc\Reply;

class Api extends \XF\Mvc\Reply\View
{
    protected $data = [];

    /**
     * @noinspection PhpMissingParentConstructorInspection
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $params)
    {
        $this->data = \XF\Util\Arr::mapMerge($this->data, $params);
    }
}
