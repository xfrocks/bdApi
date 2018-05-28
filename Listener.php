<?php

namespace Xfrocks\Api;

class Listener
{
    public static $apiDirName = 'api';
    public static $scopeDelimiter = ' ';
    public static $accessTokenParamKey = 'oauth_token';

    /**
     * @param \XF\Pub\App $app
     */
    public static function appPubSetup($app)
    {
        $container = $app->container();

        $container['api.server'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\OAuth2\Server');
            return new $class($app);
        };

        $container['api.transformer'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\Transformer');
            return new $class($app);
        };

        $container['router.api'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\Mvc\Router');
            return new $class($app);
        };

        $addOnCache = $container['addon.cache'];
        $extension = $app->extension();
        if (!empty($addOnCache['XFRM'])) {
            $extension->addClassExtension('Xfrocks\Api\Data\Modules', 'Xfrocks\Api\XFRM\Data\Modules');
        }
    }
}
