<?php

namespace Xfrocks\Api\Mvc;

use XF\App;
use Xfrocks\Api\Data\Modules;
use Xfrocks\Api\Listener;
use Xfrocks\Api\XF\Session\Session;

class Router extends \XF\Mvc\Router
{
    /**
     * @var App
     */
    protected $app;

    /**
     * @param App $app
     */
    public function __construct($app)
    {
        $this->app = $app;

        /** @var Modules $modules */
        $modules = $app->data('Xfrocks\Api:Modules');

        parent::__construct([$this, 'formatApiLink'], $modules->getRoutes());
        $this->setPather($app->container('request.pather'));
    }

    public function buildLink($link, $data = null, array $parameters = [])
    {
        if (!isset($parameters[Listener::$accessTokenParamKey])) {
            /** @var Session $session */
            $session = $this->app->session();
            $parameters[Listener::$accessTokenParamKey] = $session->getTokenText();
        }

        return parent::buildLink($link, $data, $parameters);
    }

    protected function formatApiLink($route, $queryString)
    {
        $suffix = $route . (strlen($queryString) ? '&' . $queryString : '');
        return sprintf('%s/index.php%s', Listener::$apiDirName, strlen($suffix) ? ('?' . $suffix) : '');
    }
}
