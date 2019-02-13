<?php

namespace Xfrocks\Api;

use Xfrocks\Api\OAuth2\Server;

class App extends \XF\Pub\App
{
    public function getErrorRoute($action, array $params = [], $responseType = 'html')
    {
        return new \XF\Mvc\RouteMatch('Xfrocks:Error', $action, $params, $responseType);
    }

    public function getGlobalTemplateData(\XF\Mvc\Reply\AbstractReply $reply = null)
    {
        $data = parent::getGlobalTemplateData($reply);

        $data['isApi'] = true;

        return $data;
    }

    public function initializeExtra()
    {
        parent::initializeExtra();

        $container = $this->container;

        $container['app.classType'] = 'Api';

        $container->extend('extension', function (\XF\Extension $extension) {
            $extension->addListener('dispatcher_match', ['Xfrocks\Api\Listener', 'apiOnlyDispatcherMatch']);

            return $extension;
        });

        $container->extend('extension.classExtensions', function (array $classExtensions) {
            $xfClasses = [
                'ControllerPlugin\Error',
                'Entity\User',
                'Image\Gd',
                'Image\Imagick',
                'Mvc\Dispatcher',
                'Mvc\Renderer\Json',
                'Session\Session',
                'Template\Templater',
            ];

            foreach ($xfClasses as $xfClass) {
                $extendBase = 'XF\\' . $xfClass;
                if (!isset($classExtensions[$extendBase])) {
                    $classExtensions[$extendBase] = [];
                }

                $extendClass = 'Xfrocks\Api\\' . 'XF\\ApiOnly\\' . $xfClass;
                $classExtensions[$extendBase][] = $extendClass;
            }

            return $classExtensions;
        });

        $container['request'] = function (\XF\Container $c) {
            /** @var \Xfrocks\Api\OAuth2\Server $apiServer */
            $apiServer = $this->container('api.server');
            /** @var \Symfony\Component\HttpFoundation\Request $apiRequest */
            $apiRequest = $apiServer->container('request');

            $request = new \XF\Http\Request(
                $c['inputFilterer'],
                $apiRequest->request->all() + $apiRequest->query->all(),
                $_FILES,
                []
            );

            return $request;
        };

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

        /** @var \Xfrocks\Api\XF\ApiOnly\Session\Session $apiSession */
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
