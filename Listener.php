<?php

namespace Xfrocks\Api;

class Listener
{
    public static $apiDirName = 'api';
    public static $scopeDelimiter = ' ';
    public static $accessTokenParamKey = 'oauth_token';

    /**
     * @param \XF\App $app
     */
    public static function appSetup($app)
    {
        $container = $app->container();

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

        $container['router.api'] = function () use ($app) {
            $class = $app->extendClass('Xfrocks\Api\Mvc\Router');
            return new $class($app);
        };

        $addOnCache = $container['addon.cache'];
        $extension = $app->extension();
        if (!empty($addOnCache['XFRM'])) {
            $extension->addClassExtension('Xfrocks\Api\Data\Modules', 'Xfrocks\Api\XFRM\Data\Modules');
        }

        $addOnCache = $container['addon.cache'];
        $extension = $app->extension();
        if (!empty($addOnCache['XFMG'])) {
            $extension->addClassExtension('Xfrocks\Api\Data\Modules', 'Xfrocks\Api\XFMG\Data\Modules');
        }
    }

    /**
     * @param \XF\Service\User\ContentChange $changeService
     * @param array $updates
     */
    public static function userContentChangeInit($changeService, array &$updates)
    {
        $updates['xf_bdapi_client'] = ['user_id'];
    }
}
