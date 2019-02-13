<?php

namespace Xfrocks\Api\XF\ApiOnly\Mvc;

class Dispatcher extends XFCP_Dispatcher
{
    public function getRouter()
    {
        /** @var \XF\Mvc\Router|null $router */
        $router = $this->router;

        if (!$router) {
            $router = $this->app->router('api');
            $this->router = $router;
        }

        return $router;
    }

    public function renderView(\XF\Mvc\Renderer\AbstractRenderer $renderer, \XF\Mvc\Reply\View $reply)
    {
        if ($reply instanceof \Xfrocks\Api\Mvc\Reply\Api) {
            return $reply->getData();
        }

        return parent::renderView($renderer, $reply);
    }

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
