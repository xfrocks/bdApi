<?php

namespace Xfrocks\Api\XF\ApiOnly\Mvc;

use Xfrocks\Api\Listener;

class Dispatcher extends XFCP_Dispatcher
{
    public function getRouter()
    {
        /** @var \XF\Mvc\Router|null $router */
        $router = $this->router;

        if ($router === null) {
            $router = $this->app->router(Listener::$routerType);
            $this->router = $router;
        }

        return $this->router;
    }

    /**
     * @param \XF\Mvc\Renderer\AbstractRenderer $renderer
     * @param \XF\Mvc\Reply\View $reply
     * @return array
     */
    public function renderView(\XF\Mvc\Renderer\AbstractRenderer $renderer, \XF\Mvc\Reply\View $reply)
    {
        if ($reply instanceof \Xfrocks\Api\Mvc\Reply\Api) {
            return $reply->getData();
        }

        return parent::renderView($renderer, $reply);
    }

    /**
     * @param mixed $routePath
     * @return \XF\Mvc\RouteMatch
     */
    public function route($routePath)
    {
        $match = parent::route($routePath);

        $match->setResponseType('json');

        return $match;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Dispatcher extends \XF\Mvc\Dispatcher
    {
        // extension hint
    }
}
