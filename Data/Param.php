<?php

namespace Xfrocks\Api\Data;

class Param
{
    /**
     * @var mixed
     */
    public $default = '';

    /**
     * @var string|null
     */
    public $description;

    /**
     * @var array
     */
    public $options = [];

    /**
     * @var string|null
     */
    public $type;

    /**
     * @param string|null $type
     * @param string|null $description
     */
    public function __construct($type = null, $description = null)
    {
        $this->type = $type;
        $this->description = $description;
    }
}
