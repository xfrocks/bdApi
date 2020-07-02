<?php

namespace Xfrocks\Api\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use Xfrocks\Api\Controller\AbstractController;

class ParseLink extends AbstractPlugin
{
    /**
     * @param string $link
     * @return \XF\Mvc\Reply\AbstractReply
     * @throws \Exception
     */
    public function parse($link)
    {
        /** @var AbstractController $controller */
        $controller = $this->controller;

        $request = $this->request($link);
        $fullRequestUri = $request->getFullRequestUri();

        $routed = $this->route($request);
        if (is_bool($routed)) {
            return $controller->api([
                'link' => $fullRequestUri,
                'routed' => $routed,
            ]);
        }

        $rendered = $this->render($request, $routed);
        if ($rendered !== null) {
            return $rendered;
        }

        return $controller->api([
            'link' => $request->getFullRequestUri(),
            'routed' => \XF::$debugMode ? [
                'controller' => $routed->getController(),
                'action' => $routed->getAction(),
                'params' => $routed->getParams(),
            ] : true,
        ]);
    }

    /**
     * @param string $link
     * @return \XF\Http\Request
     */
    protected function request($link)
    {
        $app = $this->app;

        $server = $_SERVER;
        $server['REQUEST_URI'] = str_replace($app->options()->boardUrl, '', $link);
        return new \XF\Http\Request($app->inputFilterer(), [], [], [], $server);
    }

    /**
     * @param \XF\Http\Request $request
     * @return bool|\XF\Mvc\RouteMatch
     * @throws \Exception
     */
    protected function route($request)
    {
        $app = $this->app;

        $dispatcherClass = $app->extendClass('XF\Mvc\Dispatcher');
        /** @var \XF\Mvc\Dispatcher $dispatcher */
        $dispatcher = new $dispatcherClass($app, $request);
        $dispatcher->setRouter($app->router('public'));

        return $dispatcher->route($request->getRoutePath());
    }

    /**
     * @param \XF\Http\Request $request
     * @param \XF\Mvc\RouteMatch $match
     * @return \XF\Mvc\Reply\AbstractReply|null
     */
    protected function render($request, $match)
    {
        $params = $match->getParams();

        switch ($match->getController()) {
            case 'XF:Forum':
                $nodeId = isset($params['node_id']) ? $params['node_id'] : null;
                if ($nodeId === null && isset($params['node_name'])) {
                    /** @var \XF\Entity\Node|null $node */
                    $node = $this->em->findOne('XF:Node', ['node_name' => $params['node_name']]);
                    if ($node !== null) {
                        $nodeId = $node->node_id;
                    }
                }

                if ($nodeId !== null) {
                    $this->request->set('forum_id', $nodeId);
                    if (isset($params['page'])) {
                        $this->request->set('page', $params['page']);
                    }

                    return $this->controller->rerouteController(
                        'Xfrocks\Api\Controller\Thread',
                        'get-index'
                    );
                }

                return $this->controller->rerouteController(
                    'Xfrocks\Api\Controller\Navigation',
                    'get-index'
                );
            case 'XF:GotoPage':
                $requestUri = $request->getRequestUri();
                $queryStr = parse_url($requestUri, PHP_URL_QUERY);
                if (!is_string($queryStr)) {
                    break;
                }
                $queryParams = \GuzzleHttp\Psr7\parse_query($queryStr);

                switch ($match->getAction()) {
                    case 'post':
                        if (isset($queryParams['id'])) {
                            $this->request->set('page_of_post_id', $queryParams['id']);
                            return $this->controller->rerouteController(
                                'Xfrocks\Api\Controller\Post',
                                'get-index'
                            );
                        }
                        break;
                    case 'convMessage':
                        // TODO
                        break;
                }
                break;
            case 'XF:Member':
                return $this->controller->rerouteController(
                    'Xfrocks\Api\Controller\User',
                    'get-index',
                    isset($params['user_id']) ? ['user_id' => $params['user_id']] : null
                );
            case 'XF:Post':
                if (isset($params['post_id'])) {
                    $this->request->set('page_of_post_id', $params['post_id']);
                    return $this->controller->rerouteController(
                        'Xfrocks\Api\Controller\Post',
                        'get-index'
                    );
                }
                break;
            case 'XF:Tag':
                if (isset($params['tag_url'])) {
                    /** @var \XF\Entity\Tag|null $tag */
                    $tag = $this->em->findOne('XF:Tag', ['tag_url' => $params['tag_url']]);
                    if ($tag !== null) {
                        return $this->controller->rerouteController(
                            'Xfrocks\Api\Controller\Tag',
                            'get-index',
                            ['tag_id' => $tag->tag_id]
                        );
                    }
                }
                break;
            case 'XF:Thread':
                if (isset($params['thread_id'])) {
                    $this->request->set('thread_id', $params['thread_id']);
                    if (isset($params['post_id'])) {
                        $this->request->set('page_of_post_id', $params['post_id']);
                    }
                    if (isset($params['page'])) {
                        $this->request->set('page', $params['page']);
                    }

                    return $this->controller->rerouteController(
                        'Xfrocks\Api\Controller\Post',
                        'get-index'
                    );
                }
                break;
        }

        return null;
    }
}
