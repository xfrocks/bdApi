<?php

namespace Xfrocks\Api\Util;

use XF\Mvc\Entity\Entity;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Transform\Selector;
use Xfrocks\Api\Transformer;

class LazyTransformer implements \JsonSerializable
{
    /**
     * @var Entity|null
     */
    protected $entity = null;

    /**
     * @var Entity[]|null
     */
    protected $entities = null;

    /**
     * @var string|null
     */
    protected $key = null;

    /**
     * @var Selector
     */
    protected $selector;

    /** @var Transformer */
    protected $transformer;

    /**
     * @param AbstractController $controller
     */
    public function __construct($controller)
    {
        /** @var Transformer $transformer */
        $this->transformer = $controller->app()->container('api.transformer');

        $params = $controller->params();
        list($exclude, $include) = $params->filterTransformSelector();
        $selector = new Selector();
        $selector->parseRules($exclude, $include);
        $this->selector = $selector;
    }

    /**
     * @return array|null
     */
    public function jsonSerialize()
    {
        return $this->transform($this->transformer, $this->selector);
    }

    /**
     * @param Entity[] $entities
     */
    public function setEntities($entities)
    {
        if ($this->entity !== null) {
            throw new \LogicException('LazyTransformer::$entity has already been set');
        }

        $this->entities = $entities;
    }

    /**
     * @param Entity $entity
     */
    public function setEntity($entity)
    {
        if ($this->entities !== null) {
            throw new \LogicException('LazyTransformer::$entities has already been set');
        }

        $this->entity = $entity;
    }

    /**
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @param Transformer $transformer
     * @param Selector $selector
     * @return array|null
     */
    public function transform($transformer, $selector)
    {
        if ($this->key !== null) {
            if ($selector->shouldExcludeField($this->key)) {
                return null;
            }

            $selector = $selector->getSubSelector($this->key);
        }

        if ($this->entities !== null) {
            $data = [];

            foreach ($this->entities as $entity) {
                $entityData = $transformer->transformEntity($selector, $entity);
                if (!is_array($entityData)) {
                    continue;
                }

                $data[] = $entityData;
            }

            return $data;
        }

        if ($this->entity !== null) {
            return $transformer->transformEntity($selector, $this->entity);
        }

        return null;
    }
}
