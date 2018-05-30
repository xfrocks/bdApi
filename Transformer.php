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

        $this->container->factory('handler', function ($shortName) {
            $class = \XF::stringToClass($shortName, '%s\Api\Transform\%s');
            $class = $this->app->extendClass($class);

            if (!class_exists($class)) {
                /** @var Modules $modules */
                $modules = $this->app->data('Xfrocks\Api:Modules');
                $class = $modules->getTransformerClass($shortName);
                $class = $this->app->extendClass($class);
            }

            if (!class_exists($class)) {
                $class = 'Xfrocks\Api\Transform\Generic';
            }

            return new $class($this->app, $this);
        });

        $this->container['custom_field_handler'] = function () {
            $class = $this->app->extendClass('Xfrocks\Api\Transform\CustomField');
            return new $class($this->app, $this);
        };
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
        $handler = $this->container->create('handler', $shortName);
        return $handler->getFetchWith($extraWith);
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
     * @param AbstractHandler $handler
     * @param \XF\CustomField\Definition $definition
     * @param mixed $value
     * @param string $key
     * @return array
     */
    public function transformCustomField(
        $handler,
        $definition,
        $value = null,
        $key = AbstractHandler::DYNAMIC_KEY_FIELDS
    ) {
        /** @var \Xfrocks\Api\Transform\CustomField $subHandler */
        $subHandler = $this->container['custom_field_handler'];
        $subHandler->reset($definition, $handler, $handler->getSubSelector($key));
        $subHandler->setValue($value);

        return $this->transform($subHandler);
    }

    /**
     * @param Selector $selector
     * @param Entity $entity
     * @return array|null
     */
    public function transformEntity($selector, $entity)
    {
        $handler = $this->getHandler($entity, null, $selector);
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
        $handler = $this->getHandler($subEntity, $handler, $handler->getSubSelector($key));
        return $this->transform($handler);
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
     * @param Entity|string $entity
     * @param AbstractHandler|null $parentHandler
     * @param Selector|null $selector
     * @return AbstractHandler
     */
    protected function getHandler($entity, $parentHandler, $selector)
    {
        $shortName = is_string($entity) ? $entity : $entity->structure()->shortName;

        /** @var AbstractHandler $handler */
        $handler = $this->container->create('handler', $shortName);

        $handler->reset(is_object($entity) ? $entity : null, $parentHandler, $selector);

        return $handler;
    }

    /**
     * @param AbstractHandler $handler
     * @return array|null
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
                $value = $handler->getEntityValue($key);
            } else {
                $value = $handler->calculateDynamicValue($mapping);
            }
            if ($value !== null) {
                $data[$mapping] = $value;
            }
        }

        if (!$handler->shouldExcludeField(AbstractHandler::KEY_LINKS)) {
            $links = $handler->collectLinks();
            $this->transformValues($handler, $data, AbstractHandler::KEY_LINKS, $links);
        }

        if (!$handler->shouldExcludeField(AbstractHandler::KEY_PERMISSIONS)) {
            $permissions = $handler->collectPermissions();
            $this->transformValues($handler, $data, AbstractHandler::KEY_PERMISSIONS, $permissions);
        }

        return $data;
    }

    /**
     * @param AbstractHandler $handler
     * @param array $data
     * @param string $key
     * @param array $values
     */
    protected function transformValues($handler, array &$data, $key, $values)
    {
        if (!is_array($values) || count($values) === 0) {
            return;
        }

        $data[$key] = [];
        $selector = $handler->getSubSelector($key);

        foreach ($values as $subKey => $value) {
            if ($selector->shouldExcludeField($subKey)) {
                continue;
            }
            if (is_array($value)) {
                $value = call_user_func($value, $handler, $subKey);
            }
            if ($value === null) {
                continue;
            }
            $data[$key][$subKey] = $value;
        }
    }
}
