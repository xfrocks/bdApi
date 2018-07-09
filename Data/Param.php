<?php

namespace Xfrocks\Api\Data;

use Xfrocks\Api\Controller\AbstractController;

class Param
{
    /**
     * @var mixed
     */
    protected $default = null;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string|null
     */
    protected $type;

    /**
     * @param string $key
     * @param string $type
     * @param string $description
     */
    public function __construct($key, $type = null, $description = null)
    {
        $this->key = $key;
        $this->type = $type;
        $this->description = $description;
    }

    /**
     * @param AbstractController $controller
     * @return mixed
     */
    public function filter($controller)
    {
        return $controller->request()->filter($this->key, $this->type, $this->default);
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $default
     * @return $this
     */
    public function withDefault($default)
    {
        $this->default = $default;
        return $this;
    }
}
