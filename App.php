<?php

namespace Xfrocks\Api;

use Xfrocks\Api\OAuth2\Server;

class App extends \XF\Pub\App
{
    public function getErrorRoute($action, array $params = [], $responseType = 'html')
    {
        return new \XF\Mvc\RouteMatch('Xfrocks:Error', $action, $params, $responseType);
    }

    public function initializeExtra()
    {
        parent::initializeExtra();

        $container = $this->container;

        $container['app.classType'] = 'Api';

        $container['em'] = function (\XF\Container $c) {
            // TODO: find a better way to extend entity manager
            return new \Xfrocks\Api\Mvc\Entity\Manager($c['db'], $c['em.valueFormatter'], $c['extension']);
        };

        $container->extend('extension.classExtensions', function (array $classExtensions) {
            $classes = [
                'XF\ControllerPlugin\Error',
                'XF\Mvc\Dispatcher',
                'XF\Mvc\Renderer\Json',
                'XF\Session\Session'
            ];

            foreach ($classes as $base) {
                if (!isset($classExtensions[$base])) {
                    $classExtensions[$base] = [];
                }

                $classExtensions[$base][] = 'Xfrocks\Api\\' . $base;
            }

            return $classExtensions;
        });

        $container->extend('request.paths', function (array $paths) {
            // move base directory up one level for URL building
            // TODO: make the change directly at XF\Http\Request::getBaseUrl
            $apiDirNameRegEx = '#' . preg_quote(Listener::$apiDirName, '#') . '/$#';
            $paths['full'] = preg_replace($apiDirNameRegEx, '', $paths['full']);
            $paths['base'] = preg_replace($apiDirNameRegEx, '', $paths['base']);

            return $paths;
        });

        $container->extend('request.pather', function ($pather) {
            return function ($url, $modifier = 'full') use ($pather) {
                // always use canonical/full URL in api context
                if ($modifier !== 'canonical') {
                    $modifier = 'full';
                }

                return $pather($url, $modifier);
            };
        });
    }

    protected function onSessionCreation(\XF\Session\Session $session)
    {
        /** @var Server $apiServer */
        $apiServer = $this->container('api.server');
        $accessToken = $apiServer->parseRequest();

        /** @var \Xfrocks\Api\XF\Session\Session $apiSession */
        $apiSession = $session;
        $apiSession->setToken($accessToken ? $accessToken->getXfToken() : null);
    }

    protected function updateModeratorCaches()
    {
        // no op
    }

    protected function updateUserCaches()
    {
        // no op
    }
}
