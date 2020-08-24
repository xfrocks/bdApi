<?php

namespace Xfrocks\Api\Mvc;

use XF\App;
use Xfrocks\Api\Data\Modules;
use Xfrocks\Api\Listener;

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

    /**
     * @param mixed $link
     * @param mixed|null $data
     * @param array $parameters
     * @param mixed|null $hash
     * @return string
     */
    public function buildLink($link, $data = null, array $parameters = [], $hash = null)
    {
        if (!isset($parameters[Listener::$accessTokenParamKey])) {
            /** @var mixed $session */
            $session = $this->app->session();
            $getTokenText = [$session, 'getTokenText'];
            if (is_callable($getTokenText)) {
                $parameters[Listener::$accessTokenParamKey] = call_user_func($getTokenText);
            }
        }

        return parent::buildLink($link, $data, $parameters, $hash);
    }

    /**
     * @param string $route
     * @param string $queryString
     * @return string
     */
    protected function formatApiLink($route, $queryString)
    {
        $suffix = $route . (strlen($queryString) > 0 ? '&' . $queryString : '');
        return sprintf('%s/index.php%s', Listener::$apiDirName, strlen($suffix) > 0 ? ('?' . $suffix) : '');
    }
}
