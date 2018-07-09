<?php

namespace Xfrocks\Api\XF\Mvc;

class Dispatcher extends XFCP_Dispatcher
{
    public function dispatchClass(
        $controllerClass,
        $action,
        $responseType,
        \XF\Mvc\ParameterBag $params = null,
        $sectionContext = null,
        &$controller = null,
        \XF\Mvc\Reply\AbstractReply $previousReply = null
    ) {
        if ($controllerClass !== 'Xfrocks:Error') {
            $method = $this->getApiMethod();
            switch ($method) {
                case 'head':
                    $method = 'get';
                    break;
                case 'options':
                    $params = new \XF\Mvc\ParameterBag(['action' => $action]);
                    $action = 'generic';
                    break;
            }

            $action = sprintf('%s/%s', $method, $action);
        }

        return parent::dispatchClass(
            $controllerClass,
            $action,
            $responseType,
            $params,
            $sectionContext,
            $controller,
            $previousReply
        );
    }

    public function getRouter()
    {
        /** @var Router|null $router */
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

    /**
     * @return string
     */
    protected function getApiMethod()
    {
        $requestMethod = strtolower($this->request->getServer('REQUEST_METHOD'));

        if ($requestMethod === 'get' && \XF::$debugMode) {
            $paramMethod = $this->request->filter('_xfApiMethod', 'str');
            if (!empty($paramMethod)) {
                return strtolower($paramMethod);
            }
        }

        return $requestMethod;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Dispatcher extends \XF\Mvc\Dispatcher
    {
        // extension hint
    }
}
