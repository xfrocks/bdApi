<?php

namespace Xfrocks\Api;

use XF\Container;
use XF\Mvc\Entity\Entity;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Data\Modules;
use Xfrocks\Api\Transformer\AbstractHandler;

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
            $class = \XF::stringToClass($shortName, '%s\Api\Transformer\%s');
            $class = $this->app->extendClass($class);

            if (!class_exists($class)) {
                /** @var Modules $modules */
                $modules = $this->app->data('Xfrocks\Api:Modules');
                $class = $modules->getTransformerClass($shortName);
                $class = $this->app->extendClass($class);
            }

            if (!class_exists($class)) {
                $class = 'Xfrocks\Api\Transformer\Generic';
            }

            return new $class($this->app, $this);
        });

        $this->container['custom_field_handler'] = function () {
            $class = $this->app->extendClass('Xfrocks\Api\Transformer\CustomField');
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
        $handler = $this->getHandler($shortName);
        return $handler->getFetchWith($extraWith);
    }

    /**
     * @param AbstractHandler $handler
     * @param \XF\Entity\Attachment[] $attachments
     * @return array
     */
    public function transformAttachments($handler, $attachments)
    {
        $data = [];

        foreach ($attachments as $attachment) {
            $data[] = $this->transformSubEntity($handler, $attachment);
        }

        return $data;
    }

    /**
     * @param AbstractHandler $handler
     * @param \XF\CustomField\Definition $definition
     * @param mixed $value
     * @return array
     */
    public function transformCustomField($handler, $definition, $value = null)
    {
        /** @var \Xfrocks\Api\Transformer\CustomField $subHandler */
        $subHandler = $this->container['custom_field_handler'];
        $subHandler->reset($definition, $handler);
        $subHandler->setValue($value);

        return $this->transform($subHandler);
    }

    /**
     * @param AbstractController $controller
     * @param Entity $entity
     * @return array|null
     */
    public function transformEntity($controller, $entity)
    {
        $handler = $this->getHandler($entity);
        return $this->transform($handler);
    }

    /**
     * @param AbstractHandler $handler
     * @param Entity $subEntity
     * @return array
     */
    public function transformSubEntity($handler, $subEntity)
    {
        $handler = $this->getHandler($subEntity, $handler);
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
     * @return AbstractHandler
     */
    protected function getHandler($entity, $parentHandler = null)
    {
        $shortName = is_string($entity) ? $entity : $entity->structure()->shortName;

        /** @var AbstractHandler $handler */
        $handler = $this->container->create('handler', $shortName);

        $handler->reset(is_object($entity) ? $entity : null, $parentHandler);

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
            if (is_string($key)) {
                $data[$mapping] = $handler->getEntityValue($key);
            } else {
                $value = $handler->calculateDynamicValue($mapping);
                if ($value !== null) {
                    $data[$mapping] = $value;
                }
            }
        }

        $links = $handler->collectLinks();
        if (is_array($links) && count($links) > 0) {
            $data[AbstractHandler::KEY_LINKS] = $links;
        }

        $permissions = $handler->collectPermissions();
        if (is_array($permissions) && count($permissions)) {
            $data[AbstractHandler::KEY_PERMISSIONS] = $permissions;
        }

        if (!$handler->postTransform($data)) {
            return null;
        }

        return $data;
    }
}
