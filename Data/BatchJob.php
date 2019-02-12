<?php

namespace Xfrocks\Api\Data;

class BatchJob
{
    /**
     * @var \XF\App
     */
    protected $app;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @param \XF\App $app
     * @param string $method
     * @param array $params
     * @param string $uri
     */
    public function __construct($app, $method, array $params, $uri)
    {
        $this->app = $app;
        $this->method = $method;
        $this->params = $params;
        $this->uri = $uri;
    }

    /**
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function execute()
    {
        $request = $this->buildRequest();
        $dispatcher = $this->buildDispatcher($request);

        $routePath = $request->getRoutePath();
        $match = $dispatcher->route($routePath);
        $reply = $dispatcher->dispatchLoop($match);

        return $reply;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return \XF\Http\Request
     */
    protected function buildRequest()
    {
        $app = $this->app;
        $appRequest = $app->request();
        $inputFilterer = $app->inputFilterer();

        $server = $_SERVER;
        $server['REQUEST_METHOD'] = $this->method;
        $absoluteUri = $appRequest->convertToAbsoluteUri($this->uri);
        $requestUri = str_replace($appRequest->getHostUrl(), '', $absoluteUri);
        $server['REQUEST_URI'] = $requestUri;

        $jobRequest = new \XF\Http\Request($inputFilterer, $this->params, [], [], $server);
        $jobRequest->set('_isApiJob', true);

        return $jobRequest;
    }

    /**
     * @param \XF\Http\Request $request
     * @return \XF\Mvc\Dispatcher
     */
    protected function buildDispatcher($request)
    {
        $app = $this->app;
        try {
            $class = $app->extendClass('XF\Mvc\Dispatcher');
            $dispatcher = new $class($app, $request);

            return $dispatcher;
        } catch (\Exception $e) {
            throw new \RuntimeException('', 0, $e);
        }
    }
}
