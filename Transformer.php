<?php

namespace Xfrocks\Api;

use XF\Container;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Data\Modules;
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
     * @param AbstractController $controller
     * @param string $shortName
     * @param array $extraWith
     * @return array
     */
    public function getFetchWith($controller, $shortName, array $extraWith = [])
    {
        /** @var AbstractHandler $handler */
        $handler = $this->handler($shortName);
        return $handler->getFetchWith($extraWith);
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
     * @param AbstractHandler $handler
     * @param string $key
     * @param array $values
     * @return array
     */
    public function transformArray($handler, $key, array $values)
    {
        $subHandler = $this->handler();
        $subSelector = $handler->getSubSelector($key);
        $subHandler->reset($values, $handler, $subSelector);
        return $this->transform($subHandler);
    }

    /**
     * @param AbstractHandler $handler
     * @param ArrayCollection|\XF\Entity\Attachment[] $attachments
     * @param string $key
     * @return array
     */
    public function transformAttachments($handler, $attachments, $key = AbstractHandler::DYNAMIC_KEY_ATTACHMENTS)
    {
        return $this->transformSubEntities($handler, $key, $attachments);
    }

    /**
     * @param \XF\Mvc\Reply\AbstractReply $reply
     * @return array
     */
    public function transformBatchJobReply($reply)
    {
        /** @var \Xfrocks\Api\Transform\BatchJobReply $handler */
        $handler = $this->handler('Xfrocks:BatchJobReply');
        $handler->reset($reply, null, null);
        return $this->transform($handler);
    }

    /**
     * @param AbstractHandler $handler
     * @param \XF\CustomField\DefinitionSet $set
     * @param string $key
     * @return array
     */
    public function transformCustomFieldDefinitionSet($handler, $set, $key = AbstractHandler::DYNAMIC_KEY_FIELDS)
    {
        /** @var \Xfrocks\Api\Transform\CustomField $subHandler */
        $subHandler = $this->handler('Xfrocks:CustomField');
        $subSelector = $handler->getSubSelector($key);

        $data = [];
        foreach ($set->getIterator() as $definition) {
            $subHandler->reset($definition, $handler, $subSelector);
            $subHandler->setValue(null);

            $data[] = $this->transform($subHandler);
        }

        return $data;
    }

    /**
     * @param AbstractHandler $handler
     * @param \XF\CustomField\Set $set
     * @param string $key
     * @return array
     */
    public function transformCustomFieldSet($handler, $set, $key = AbstractHandler::DYNAMIC_KEY_FIELDS)
    {
        $definitionSet = $set->getDefinitionSet();
        /** @var \Xfrocks\Api\Transform\CustomField $subHandler */
        $subHandler = $this->handler('Xfrocks:CustomField');
        $subSelector = $handler->getSubSelector($key);

        $data = [];
        foreach ($set->getIterator() as $field => $value) {
            if (!isset($definitionSet[$field])) {
                continue;
            }
            $definition = $definitionSet[$field];

            $subHandler->reset($definition, $handler, $subSelector);
            $subHandler->setValue($value);

            $data[] = $this->transform($subHandler);
        }

        return $data;
    }

    /**
     * @param Selector $selector
     * @param Entity $entity
     * @return array|null
     */
    public function transformEntity($selector, $entity)
    {
        $handler = $this->handler($entity->structure()->shortName);
        $handler->reset($entity, null, $selector);
        return $this->transform($handler);
    }

    /**
     * @param AbstractHandler $handler
     * @param string $key
     * @param ArrayCollection|Entity[] $subEntities
     * @return array
     */
    public function transformSubEntities($handler, $key, $subEntities)
    {
        $data = [];
        foreach ($subEntities as $subEntity) {
            $data[] = $this->transformSubEntity($handler, $key, $subEntity);
        }
        return $data;
    }

    /**
     * @param AbstractHandler $handler
     * @param string $key
     * @param Entity $subEntity
     * @return array
     */
    public function transformSubEntity($handler, $key, $subEntity)
    {
        $subHandler = $this->handler($subEntity->structure()->shortName);
        $subSelector = $handler->getSubSelector($key);
        $subHandler->reset($subEntity, $handler, $subSelector);
        return $this->transform($subHandler);
    }

    /**
     * @param AbstractHandler $handler
     * @param array $tags
     * @return array
     */
    public function transformTags($handler, $tags)
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
     * @param AbstractHandler $handler
     * @param string $key
     * @param array $values
     * @param array $data
     */
    protected function addArrayToData($handler, $key, $values, array &$data)
    {
        if (!is_array($values) || count($values) === 0) {
            return;
        }

        $transformed = $this->transformArray($handler, $key, $values);
        if (count($transformed) === 0) {
            return;
        }

        $data[$key] = $transformed;
    }

    /**
     * @param AbstractHandler $handler
     * @return array
     */
    protected function transform($handler)
    {
        $data = [];

        $mappings = $handler->getMappings();
        foreach ($mappings as $key => $mapping) {
            if ($handler->shouldExcludeField($mapping)) {
                continue;
            }

            $value = null;
            if (is_string($key)) {
                $value = $handler->getSourceValue($key);
            } else {
                $value = $handler->calculateDynamicValue($mapping);
            }
            if ($value !== null) {
                $data[$mapping] = $value;
            }
        }

        if (!$handler->shouldExcludeField(AbstractHandler::KEY_LINKS)) {
            $links = $handler->collectLinks();
            $this->addArrayToData($handler, AbstractHandler::KEY_LINKS, $links, $data);
        }

        if (!$handler->shouldExcludeField(AbstractHandler::KEY_PERMISSIONS)) {
            $permissions = $handler->collectPermissions();
            $this->addArrayToData($handler, AbstractHandler::KEY_PERMISSIONS, $permissions, $data);
        }

        return $data;
    }
}
