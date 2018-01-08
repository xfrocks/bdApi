<?php

namespace Xfrocks\Api\Transformer;

use XF\App;
use XF\Mvc\Entity\Entity;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Transformer;
use Xfrocks\Api\XF\Session\Session;

abstract class AbstractHandler
{
    const KEY_LINKS = 'links';
    const KEY_PERMISSIONS = 'permissions';

    /**
     * @var App
     */
    protected $app;

    /**
     * @var Entity
     */
    protected $entity;

    /**
     * @var Transformer
     */
    protected $transformer;

    /**
     * @param App $app
     * @param Transformer $transformer
     */
    public function __construct($app, $transformer)
    {
        $this->app = $app;
        $this->transformer = $transformer;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function calculateDynamicValue($key)
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function collectLinks()
    {
        return null;
    }

    /**
     * @return mixed
     */
    public function collectPermissions()
    {
        return null;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getEntityValue($key)
    {
        return $this->entity->get($key);
    }

    /**
     * @return array
     */
    public function getFetchWith()
    {
        return [];
    }

    /**
     * @return array
     */
    public function getMappings()
    {
        return [];
    }

    /**
     * @param Entity $entity
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
    }

    /**
     * @return array
     */
    public function transformEntity()
    {
        $mappings = $this->getMappings();
        $data = $this->transformer->transformValues($this, $mappings);

        $links = $this->collectLinks();
        if (is_array($links) && count($links) > 0) {
            $data[self::KEY_LINKS] = $links;
        }

        $permissions = $this->collectPermissions();
        if (is_array($permissions) && count($permissions)) {
            $data[self::KEY_PERMISSIONS] = $permissions;
        }

        return $data;
    }

    /**
     * @param string $link
     * @param mixed $data
     * @param array $parameters
     * @return string
     */
    protected function buildApiLink($link, $data = null, array $parameters = [])
    {
        $apiRouter = $this->app->router('api');
        return $apiRouter->buildLink($link, $data, $parameters);
    }

    /**
     * @param string $link
     * @param mixed $data
     * @param array $parameters
     * @return string
     */
    protected function buildPublicLink($link, $data = null, array $parameters = [])
    {
        $publicRouter = $this->app->router('public');
        return $publicRouter->buildLink($link, $data, $parameters);
    }

    /**
     * @param string $scope
     * @return bool
     */
    protected function checkSessionScope($scope)
    {
        /** @var Session $session */
        $session = $this->app->session();
        return is_callable([$session, 'hasScope']) && $session->hasScope($scope);
    }

    /**
     * @param string $permissionId
     * @return bool
     */
    protected function checkAdminPermission($permissionId)
    {
        if (!$this->checkSessionScope(Server::SCOPE_MANAGE_SYSTEM)) {
            return false;
        }

        return \XF::visitor()->hasAdminPermission($permissionId);
    }

    /**
     * @return \XF\Template\Templater
     */
    protected function getTemplater()
    {
        return $this->app->templater();
    }
}
