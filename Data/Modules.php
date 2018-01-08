<?php

namespace Xfrocks\Api\Data;

class Modules
{
    private $routes = [];
    private $versions = [
        'forum' => 2017122801,
        'oauth2' => 2016030902,
        'subscription' => 2014092301,
    ];

    public function __construct()
    {
        $this->addController('Xfrocks:Index', 'index');
        $this->addController('Xfrocks:OAuth2', 'oauth');
        $this->addController('Xfrocks:User', 'users', ':int<user_id>/');
    }

    final public function getRoutes()
    {
        return $this->routes;
    }

    final public function getVersions()
    {
        return $this->versions;
    }

    protected function addController(
        $controller,
        $prefix,
        $format = '',
        $buildCallback = null,
        $subSection = '',
        $context = '',
        $actionPrefix = ''
    ) {
        $this->addRoute($prefix, $subSection, [
            'format' => $format,
            'build_callback' => $buildCallback,
            'controller' => $controller,
            'context' => $context,
            'action_prefix' => $actionPrefix
        ]);
    }

    protected function addRoute($prefix, $subSection, $route)
    {
        if (!empty($this->routes[$prefix][$subSection])) {
            throw new \InvalidArgumentException(sprintf('Route "%s"."%s" has already existed', $prefix, $subSection));
        }

        $this->routes[$prefix][$subSection] = $route;
    }

    protected function register($id, $version)
    {
        if (!empty($this->versions[$id])) {
            throw new \InvalidArgumentException(sprintf('Module "%s" has already been registered', $id));
        }

        $this->versions[$id] = $version;
    }
}
