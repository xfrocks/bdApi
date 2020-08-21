<?php

namespace Xfrocks\Api;

class Listener
{
    /**
     * @var string
     */
    public static $accessTokenParamKey = 'oauth_token';

    /**
     * @var string
     */
    public static $apiDirName = 'api';

    /**
     * @var string
     */
    public static $routerType = 'XfrocksApi';

    /**
     * @var string
     */
    public static $scopeDelimiter = ' ';

    /**
     * @param \XF\App $app
     * @return void
     */
    public static function appSetup($app)
    {
        $container = $app->container();

        $apiConfig = $app->config('api');
        if (is_array($apiConfig)) {
            foreach ($apiConfig as $apiConfigKey => $apiConfigValue) {
                switch ($apiConfigKey) {
                    case 'accessTokenParamKey':
                    case 'apiDirName':
                    case 'routerType':
                    case 'scopeDelimiter':
                        // @phpstan-ignore-next-line
                        self::$$apiConfigKey = $apiConfigValue;
                        break;
                }
            }
        }

        if ($container->offsetExists('api.server')) {
            // temporary workaround for XF2 job.php weird-behavior
            // https://xenforo.com/community/threads/job-php-runs-app-setup-twice.153198/
            // TODO: implement permanent solution or remove this after XF is updated
            return;
        }

        $container['api.server'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\OAuth2\Server');
            return new $class($app);
        };

        $container['api.transformer'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\Transformer');
            return new $class($app);
        };

        $container['router.' . self::$routerType] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\Mvc\Router');
            return new $class($app);
        };

        $addOnCache = $container['addon.cache'];
        $extension = $app->extension();
        if (isset($addOnCache['XFRM'])) {
            $extension->addClassExtension('Xfrocks\Api\Controller\Search', 'Xfrocks\Api\XFRM\Controller\Search');
            $extension->addClassExtension('Xfrocks\Api\Data\Modules', 'Xfrocks\Api\XFRM\Data\Modules');
        }
    }

    /**
     * @param \XF\Mvc\Dispatcher $dispatcher
     * @param \XF\Mvc\RouteMatch $match
     * @return void
     */
    public static function apiOnlyDispatcherMatch($dispatcher, &$match)
    {
        if ($match->getController() !== 'Xfrocks:Error') {
            $request = $dispatcher->getRequest();

            $action = $match->getAction();
            $method = strtolower($request->getServer('REQUEST_METHOD'));
            if ($method === 'get' && \XF::$debugMode) {
                $methodDebug = $request->filter('_xfApiMethod', 'str');
                if ($methodDebug !== '') {
                    $method = strtolower($methodDebug);
                }
            }

            switch ($method) {
                case 'head':
                    $method = 'get';
                    break;
                case 'options':
                    $match->setParam('action', $match->getAction());
                    $action = 'generic';
                    break;
            }

            $match->setAction(sprintf('%s/%s', $method, $action));
        }
    }

    /**
     * @param \XF\Service\User\ContentChange $changeService
     * @param array $updates
     * @return void
     */
    public static function userContentChangeInit($changeService, array &$updates)
    {
        $updates['xf_bdapi_client'] = ['user_id'];
    }
}
