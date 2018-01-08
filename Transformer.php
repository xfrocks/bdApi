<?php

namespace Xfrocks\Api;

use XF\Container;
use XF\Mvc\Entity\Entity;
use Xfrocks\Api\Controller\AbstractController;
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
                $class = \XF::stringToClass($shortName, 'Xfrocks\Api\Transformer\%s\%s');
                $class = $this->app->extendClass($class);
            }

            if (!class_exists($class)) {
                $class = 'Xfrocks\Api\Transformer\Generic';
            }

            return new $class($this->app, $this);
        });
    }

    /**
     * @param AbstractController $controller
     * @param string $shortName
     * @return array
     */
    public function getFetchWith($controller, $shortName)
    {
        $handler = $this->getHandler($shortName);

        return $handler->getFetchWith();
    }

    /**
     * @param AbstractController $controller
     * @param Entity $entity
     * @return array
     */
    public function transformEntity($controller, $entity)
    {
        $handler = $this->getHandler($entity->structure()->shortName);
        $handler->setEntity($entity);

        return $handler->transformEntity();
    }

    /**
     * @param AbstractHandler $handler
     * @param Entity $subEntity
     * @return array
     */
    public function transformSubEntity($handler, $subEntity)
    {
        $subHandler = $this->getHandler($subEntity->structure()->shortName);
        $subHandler->setEntity($subEntity);

        return $subHandler->transformEntity();
    }

    /**
     * @param AbstractHandler $handler
     * @param array $mappings
     * @return array
     */
    public function transformValues($handler, array $mappings)
    {
        $data = [];

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

        return $data;
    }

    /**
     * @param string $shortName
     * @return AbstractHandler
     */
    protected function getHandler($shortName)
    {
        return $this->container->create('handler', $shortName);
    }
}
