<?php

namespace Xfrocks\Api;

use XF\Container;
use XF\Mvc\Entity\Entity;
use Xfrocks\Api\Data\Modules;
use Xfrocks\Api\Data\TransformContext;
use Xfrocks\Api\Transform\AbstractHandler;
use Xfrocks\Api\Transform\Selector;

class Transformer
{
    /**
     * @var \XF\App
     */
    protected $app;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @param \XF\App $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->container = new Container();

        $this->container->factory('handler', function ($type) {
            $class = \XF::stringToClass($type, '%s\Api\Transform\%s');
            $class = $this->app->extendClass($class);

            if (!class_exists($class)) {
                /** @var Modules $modules */
                $modules = $this->app->data('Xfrocks\Api:Modules');
                $class = $modules->getTransformerClass($type);
                $class = $this->app->extendClass($class);
            }

            if (!class_exists($class)) {
                $class = 'Xfrocks\Api\Transform\Generic';
            }

            return new $class($this->app, $this);
        });
    }

    /**
     * @param string $type
     * @return AbstractHandler
     */
    public function handler($type = '')
    {
        return $this->container->create('handler', $type);
    }

    /**
     * @param TransformContext $context
     * @param string $key
     * @param array $values
     * @return array
     */
    public function transformArray($context, $key, array $values)
    {
        $subContext = $context->getSubContext($key, $this->handler(), $values);
        return $this->transform($subContext);
    }

    /**
     * @param \XF\Mvc\Reply\AbstractReply $reply
     * @return array
     */
    public function transformBatchJobReply($reply)
    {
        /** @var \Xfrocks\Api\Transform\BatchJobReply $handler */
        $handler = $this->handler('Xfrocks:BatchJobReply');
        $context = new TransformContext($handler, $reply);
        return $this->transform($context);
    }

    /**
     * @param TransformContext $context
     * @param \XF\CustomField\DefinitionSet $set
     * @param string $key
     * @return array
     */
    public function transformCustomFieldDefinitionSet($context, $set, $key = AbstractHandler::DYNAMIC_KEY_FIELDS)
    {
        /** @var \Xfrocks\Api\Transform\CustomField $subHandler */
        $subHandler = $this->handler('Xfrocks:CustomField');

        $data = [];
        foreach ($set->getIterator() as $definition) {
            $subContext = $context->getSubContext($key, $subHandler, [$definition]);
            $data[] = $this->transform($subContext);
        }

        return $data;
    }

    /**
     * @param TransformContext $context
     * @param \XF\CustomField\Set $set
     * @param string $key
     * @return array
     */
    public function transformCustomFieldSet($context, $set, $key = AbstractHandler::DYNAMIC_KEY_FIELDS)
    {
        $definitionSet = $set->getDefinitionSet();
        /** @var \Xfrocks\Api\Transform\CustomField $subHandler */
        $subHandler = $this->handler('Xfrocks:CustomField');

        $data = [];
        foreach ($set->getIterator() as $field => $value) {
            if (!isset($definitionSet[$field])) {
                continue;
            }
            $definition = $definitionSet[$field];

            $subContext = $context->getSubContext($key, $subHandler, [$definition, $value]);
            $data[] = $this->transform($subContext);
        }

        return $data;
    }

    /**
     * @param Entity $entity
     * @param Selector|null $selector
     * @return array
     */
    public function transformEntity($entity, $selector = null)
    {
        $canView = [$entity, 'canView'];
        if (is_callable($canView) && call_user_func($canView) !== true) {
            // make sure to never accidentally transform protected entity data
            return [];
        }

        $handler = $this->handler($entity->structure()->shortName);
        $context = new TransformContext($handler, $entity, $selector);
        return $this->transform($context);
    }

    /**
     * @param TransformContext $context
     * @param string $key
     * @param Entity $entity
     * @param string $relationKey
     * @return array
     */
    public function transformEntityRelation($context, $key, $entity, $relationKey)
    {
        $entityStructure = $entity->structure();
        if (!isset($entityStructure->relations[$relationKey])) {
            return [];
        }

        $relationConfig = $entityStructure->relations[$relationKey];
        if (!is_array($relationConfig) ||
            !isset($relationConfig['type']) ||
            !isset($relationConfig['entity'])
        ) {
            return [];
        }

        $relationData = $entity->getRelation($relationKey);
        if ($relationConfig['type'] === Entity::TO_ONE) {
            /** @var Entity $subEntity */
            $subEntity = $relationData;
            return $this->transformSubEntity($context, $key, $subEntity);
        }

        $subHandler = $this->handler($relationConfig['entity']);

        $data = [];
        /** @var Entity[] $subEntities */
        $subEntities = $relationData;
        $subSelector = $context->handler->getSubSelector($context->selector, $key);
        $subEntities = $subHandler->onTransformEntities($subEntities, $subSelector);

        foreach ($subEntities as $subEntity) {
            $subContext = $context->getSubContext($key, $subHandler, $subEntity);
            $subEntityData = $this->transform($subContext);
            if (count($subEntityData) > 0) {
                $data[] = $subEntityData;
            }
        }

        return $data;
    }

    /**
     * @param \Exception $exception
     * @return array
     */
    public function transformException($exception)
    {
        $handler = $this->handler('Xfrocks:Exception');
        $context = new TransformContext($handler, $exception);
        return $this->transform($context);
    }

    /**
     * @param TransformContext $context
     * @param string $key
     * @param Entity $subEntity
     * @return array
     */
    public function transformSubEntity($context, $key, $subEntity)
    {
        $subHandler = $this->handler($subEntity->structure()->shortName);
        $subContext = $context->getSubContext($key, $subHandler, $subEntity);
        return $this->transform($subContext);
    }

    /**
     * @param TransformContext $context
     * @param array $tags
     * @return array
     */
    public function transformTags($context, $tags)
    {
        if (!is_array($tags) || count($tags) === 0) {
            return [];
        }

        $data = [];

        foreach ($tags as $tagId => $tag) {
            $data[strval($tagId)] = $tag['tag'];
        }

        return $data;
    }

    /**
     * @param TransformContext $context
     * @param string $key
     * @param array|null $values
     * @param array $data
     */
    protected function addArrayToData($context, $key, $values, array &$data)
    {
        if (!is_array($values) || count($values) === 0) {
            return;
        }

        $transformed = $this->transformArray($context, $key, $values);
        if (count($transformed) === 0) {
            return;
        }

        $data[$key] = $transformed;
    }

    /**
     * @param TransformContext $context
     * @return array
     */
    protected function transform($context)
    {
        $data = [];
        $handler = $context->handler;
        $context->contextData = $handler->onNewContext($context);

        $mappings = $handler->getMappings($context);
        foreach ($mappings as $key => $mapping) {
            if ($handler->shouldExcludeField($context, $mapping)) {
                continue;
            }

            $value = null;
            if (is_string($key)) {
                $value = $context->getSourceValue($key);
            } else {
                $value = $handler->calculateDynamicValue($context, $mapping);
            }
            if ($value !== null) {
                $data[$mapping] = $value;
            }
        }

        if (!$handler->shouldExcludeField($context, AbstractHandler::KEY_LINKS)) {
            $links = $handler->collectLinks($context);
            $this->addArrayToData($context, AbstractHandler::KEY_LINKS, $links, $data);
        }

        if (!$handler->shouldExcludeField($context, AbstractHandler::KEY_PERMISSIONS)) {
            $permissions = $handler->collectPermissions($context);
            $this->addArrayToData($context, AbstractHandler::KEY_PERMISSIONS, $permissions, $data);
        }

        return $data;
    }
}
