<?php

namespace Xfrocks\Api\Mvc\Reply;

class Api extends \XF\Mvc\Reply\View
{
    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct('', '', $data);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->params;
    }

    /**
     * @param array $data
     * @param bool $merge
     * @return void
     */
    public function setData(array $data, $merge = true)
    {
        $this->setParams($data, $merge);
    }
}
