<?php

namespace Xfrocks\Api\Util;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Transform\Selector;
use Xfrocks\Api\Transformer;

class LazyTransformer
{
    const SOURCE_TYPE_ENTITIES = 'entities';
    const SOURCE_TYPE_ENTITY = 'entity';
    const SOURCE_TYPE_FINDER = 'finder';

    /**
     * @var array|null
     */
    protected $finderSortByList = null;

    /**
     * @var string|null
     */
    protected $key = null;

    /**
     * @var Selector
     */
    protected $selector;

    /**
     * @var mixed
     */
    protected $source = null;

    /**
     * @var string
     */
    protected $sourceType = '';

    /**
     * @var Transformer
     */
    protected $transformer;

    /**
     * @param AbstractController $controller
     */
    public function __construct($controller)
    {
        $this->transformer = $controller->app()->container('api.transformer');
        $this->selector = $controller->params()->parseSelectorRules();
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
        if ($this->source !== null) {
            throw new \LogicException('Source has already been set: ' . $this->sourceType);
        }

        $this->source = $entities;
        $this->sourceType = self::SOURCE_TYPE_ENTITIES;
    }

    /**
     * @param Entity $entity
     */
    public function setEntity(Entity $entity)
    {
        if ($this->source !== null) {
            throw new \LogicException('Source has already been set: ' . $this->sourceType);
        }

        $this->source = $entity;
        $this->sourceType = self::SOURCE_TYPE_ENTITY;
    }

    /**
     * @param Finder $finder
     */
    public function setFinder(Finder $finder)
    {
        if ($this->source !== null) {
            throw new \LogicException('Source has already been set: ' . $this->sourceType);
        }

        $this->source = $finder;
        $this->sourceType = self::SOURCE_TYPE_FINDER;
    }

    /**
     * @param string $key
     */
    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * @param array $keys
     * @return LazyTransformer
     */
    public function sortByList(array $keys)
    {
        if ($this->sourceType !== self::SOURCE_TYPE_FINDER) {
            throw new \LogicException('Source is not a Finder: ' . $this->sourceType);
        }

        $this->finderSortByList = $keys;
        return $this;
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

        switch ($this->sourceType) {
            case self::SOURCE_TYPE_ENTITIES:
                /** @var Entity[] $entities */
                $entities = $this->source;

                $shortName = null;
                foreach ($entities as $entity) {
                    $shortName = $entity->structure()->shortName;
                    break;
                }
                if ($shortName === null) {
                    return [];
                }

                return $this->transformEntities($transformer, $entities, $selector);
            case self::SOURCE_TYPE_ENTITY:
                /** @var Entity $entity */
                $entity = $this->source;
                return $transformer->transformEntity($entity, $selector);
            case self::SOURCE_TYPE_FINDER:
                /** @var Finder $finder */
                $finder = $this->source;
                $handler = $transformer->handler($finder->getStructure()->shortName);
                $finder = $handler->onLazyTransformBeforeFetching($finder, $selector);

                $result = $finder->fetch();
                if ($this->finderSortByList !== null) {
                    $result = $result->sortByList($this->finderSortByList);
                }

                return $this->transformEntities($transformer, $result->toArray(), $selector);
        }

        throw new \LogicException('Unrecognized source type ' . $this->sourceType);
    }

    /**
     * @param Transformer $transformer
     * @param Entity[] $entities
     * @param Selector $selector
     * @return array
     */
    protected function transformEntities($transformer, $entities, $selector)
    {
        $data = [];

        $handler = null;
        foreach ($entities as $entity) {
            $handler = $transformer->handler($entity->structure()->shortName);
            break;
        }
        if ($handler === null) {
            return $data;
        }

        $handler->onLazyTransformEntities($entities, $selector);

        foreach ($entities as $entity) {
            $entityData = $transformer->transformEntity($entity, $selector);
            if (!is_array($entityData)) {
                continue;
            }

            $data[] = $entityData;
        }

        return $data;
    }
}
