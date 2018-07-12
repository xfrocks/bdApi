<?php

namespace Xfrocks\Api\Data;

use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\Selector;
use Xfrocks\Api\Transformer;

class TransformContext
{
    /**
     * @var array|null
     *
     * @see AbstractHandler::onNewContext()
     * @see Transformer::transform()
     */
    public $contextData;

    /**
     * @var AbstractHandler
     */
    public $handler;

    /**
     * @var TransformContext|null
     */
    public $parentContext;

    /**
     * @var Selector|null
     */
    public $selector;

    /**
     * @var mixed
     */
    public $source;

    /**
     * @param AbstractHandler $handler
     * @param mixed $source
     * @param Selector|null $selector
     */
    public function __construct($handler, $source, $selector = null)
    {
        $this->handler = $handler;
        $this->source = $source;
        $this->selector = $selector;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getSourceValue($key)
    {
        return $this->source !== null ? $this->source[$key] : null;
    }

    /**
     * @param string $key
     * @param AbstractHandler $subHandler
     * @param mixed $subSource
     * @return TransformContext
     */
    public function getSubContext($key, $subHandler, $subSource)
    {
        $subSelector = $this->handler->getSubSelector($this->selector, $key);
        $subContext = new TransformContext($subHandler, $subSource, $subSelector);
        $subContext->parentContext = $this;

        return $subContext;
    }
}
