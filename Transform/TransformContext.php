<?php

namespace Xfrocks\Api\Transform;

use Xfrocks\Api\Transformer;

class TransformContext
{
    /**
     * @var array
     */
    public $onTransformFinderCallbacks = [];

    /**
     * @var array
     */
    public $onTransformEntitiesCallbacks = [];

    /**
     * @var array
     */
    public $onTransformedCallbacks = [];

    /**
     * @var array
     *
     * @see AbstractHandler::onNewContext()
     * @see Transformer::transform()
     */
    protected $contextData = [];

    /**
     * @var AbstractHandler|null
     */
    protected $handler;

    /**
     * @var TransformContext|null
     */
    protected $parentContext;

    /**
     * @var TransformContext
     */
    protected $rootContext;

    /**
     * @var Selector|null
     */
    protected $selector;

    /**
     * @var Selector[]
     */
    protected $selectorByTypes = [];

    /**
     * @var Selector|null
     */
    protected $selectorDefault = null;

    /**
     * @var mixed|null
     */
    protected $source;

    /**
     * @param AbstractHandler|null $handler
     * @param mixed|null $source
     * @param Selector|null $selector
     */
    public function __construct($handler = null, $source = null, $selector = null)
    {
        $this->handler = $handler;
        $this->source = $source;
        $this->selector = $selector;

        $this->rootContext = $this;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function data($key)
    {
        if (!isset($this->contextData[$key])) {
            return null;
        }

        return $this->contextData[$key];
    }

    /**
     * @return AbstractHandler|null
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @return array
     */
    public function getOnTransformedCallbacks()
    {
        return $this->rootContext->onTransformedCallbacks;
    }

    /**
     * @return array
     */
    public function getOnTransformEntitiesCallbacks()
    {
        return $this->rootContext->onTransformEntitiesCallbacks;
    }

    /**
     * @return array
     */
    public function getOnTransformFinderCallbacks()
    {
        return $this->rootContext->onTransformFinderCallbacks;
    }

    /**
     * @return AbstractHandler|null
     */
    public function getParentHandler()
    {
        if ($this->parentContext === null) {
            return null;
        }

        return $this->parentContext->getHandler();
    }

    /**
     * @return mixed|null
     */
    public function getParentSource()
    {
        if ($this->parentContext === null) {
            return null;
        }

        return $this->parentContext->getSource();
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getParentSourceValue($key)
    {
        if ($this->parentContext === null) {
            return null;
        }

        return $this->parentContext->getSourceValue($key);
    }

    /**
     * @return mixed|null
     */
    public function getSource()
    {
        return $this->source;
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
     * @param string|null $key
     * @param AbstractHandler|null $subHandler
     * @param mixed|null $subSource
     * @return TransformContext
     */
    public function getSubContext($key = null, $subHandler = null, $subSource = null)
    {
        $subSelector = $this->selector;
        if ($subSelector !== null && $key !== null) {
            $subSelector = $subSelector->getSubSelector($key);
        }

        $subContext = new TransformContext($subHandler, $subSource, $subSelector);
        $subContext->parentContext = $this;
        $subContext->rootContext = $this->rootContext;

        if ($key === null) {
            $subContext->setData($this->contextData);
        }

        return $subContext;
    }

    /**
     * @param string $handlerType
     * @return Selector
     */
    public function makeSureSelectorIsNotNull($handlerType)
    {
        if ($this->selector === null) {
            $this->selector = $this->getSelectorDefault();
        }

        if (!$this->selector->hasRules()) {
            if (isset($this->rootContext->selectorByTypes[$handlerType])) {
                $this->selector = $this->rootContext->selectorByTypes[$handlerType];
            }
        } else {
            if (!isset($this->rootContext->selectorByTypes[$handlerType])) {
                $this->rootContext->selectorByTypes[$handlerType] = $this->selector;
            }
        }

        return $this->selector;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function selectorShouldExcludeField($key)
    {
        if ($this->selector === null) {
            return false;
        }

        return $this->selector->shouldExcludeField($key);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function selectorShouldIncludeField($key)
    {
        if ($this->selector === null) {
            return false;
        }

        return $this->selector->shouldIncludeField($key);
    }

    /**
     * @param array|mixed $data
     * @param mixed|null $key
     */
    public function setData($data, $key = null)
    {
        if ($key === null) {
            if (!is_array($data)) {
                throw new \InvalidArgumentException('$data is not an array');
            }

            $this->contextData = array_merge($this->contextData, $data);
        } else {
            $this->contextData[$key] = $data;
        }
    }

    /**
     * @return Selector
     */
    protected function getSelectorDefault()
    {
        $rootContext = $this->rootContext;

        if ($rootContext->selectorDefault === null) {
            $rootContext->selectorDefault = new Selector();
        }

        return $rootContext->selectorDefault;
    }
}
