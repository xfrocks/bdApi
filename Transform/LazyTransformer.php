<?php

namespace Xfrocks\Api\Transform;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Transformer;

class LazyTransformer implements \JsonSerializable
{
    const SOURCE_TYPE_ENTITY = 'entity';
    const SOURCE_TYPE_FINDER = 'finder';

    /**
     * @var callable[]
     */
    protected $callbacksFinderPostFetch = [];

    /**
     * @var callable[]
     */
    protected $callbacksPostTransform = [];

    /**
     * @var callable[]
     */
    protected $callbacksPreTransform = [];

    /**
     * @var AbstractController
     */
    protected $controller;

    /**
     * @var mixed|null
     */
    protected $source = null;

    /**
     * @var string
     */
    protected $sourceType = '';

    /**
     * @param AbstractController $controller
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @param callable $f
     */
    public function addCallbackFinderPostFetch($f)
    {
        if ($this->sourceType !== self::SOURCE_TYPE_FINDER) {
            throw new \LogicException('Source is not a Finder: ' . $this->sourceType);
        }

        $this->callbacksFinderPostFetch[] = $f;
    }

    /**
     * @param callable $f
     */
    public function addCallbackPostTransform($f)
    {
        $this->callbacksPostTransform[] = $f;
    }

    /**
     * @param callable $f
     */
    public function addCallbackPreTransform($f)
    {
        $this->callbacksPreTransform[] = $f;
    }

    /**
     * @return array|null
     */
    public function jsonSerialize()
    {
        return $this->transform();
    }

    public function getLogData()
    {
        switch ($this->sourceType) {
            case 'entity':
                /** @var Entity $entity */
                $entity = $this->source;
                return sprintf(
                    'LazyTransformer(%s@%d)',
                    \XF::stringToClass($entity->structure()->shortName, '%s\Entity\%s'),
                    $entity->getEntityId()
                );
            case 'finder':
                /** @var Finder $finder */
                $finder = $this->source;
                return sprintf(
                    'LazyTransformer(%s) => %s',
                    \XF::stringToClass($finder->getStructure()->shortName, '%s\Finder\%s'),
                    $finder->getQuery()
                );
            default:
                return 'LazyTransformer(' . $this->sourceType . ')';
        }
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
     * @return array|null
     */
    public function transform()
    {
        $controller = $this->controller;
        /** @var Transformer $transformer */
        $transformer = $controller->app()->container('api.transformer');
        $context = $controller->params()->getTransformContext();

        foreach (array_reverse($this->callbacksPreTransform) as $f) {
            $context = call_user_func($f, $context);
            if ($context === null) {
                return null;
            }
        }

        switch ($this->sourceType) {
            case self::SOURCE_TYPE_ENTITY:
                /** @var Entity $entity */
                $entity = $this->source;
                $data = $transformer->transformEntity($context, null, $entity);
                break;
            case self::SOURCE_TYPE_FINDER:
                /** @var Finder $finder */
                $finder = $this->source;
                $data = $transformer->transformFinder($context, null, $finder, function ($entities) {
                    foreach (array_reverse($this->callbacksFinderPostFetch) as $f) {
                        $entities = call_user_func($f, $entities);
                    }

                    return $entities;
                });
                break;
            default:
                throw new \LogicException('Unrecognized source type ' . $this->sourceType);
        }

        foreach (array_reverse($this->callbacksPostTransform) as $f) {
            $data = call_user_func($f, $data);
            if ($data === null) {
                return null;
            }
        }

        return $data;
    }
}
