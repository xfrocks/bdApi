<?php

namespace Xfrocks\Api\Controller;

use Xfrocks\Api\App;
use Xfrocks\Api\Data\Modules;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\XF\Session\Session;

class Index extends AbstractController
{
    public function actionGetIndex()
    {
        /** @var App $app */
        $app = $this->app;
        $apiRouter = $app->router('api');
        $publicRouter = $app->router('public');
        /** @var Session $session */
        $session = $this->session();
        $visitor = \XF::visitor();

        $systemInfo = [];
        $sessionToken = $session->getToken();
        if (empty($sessionToken)) {
            $systemInfo += [
                'oauth/authorize' => $publicRouter->buildLink('account/authorize'),
                'oauth/token' => $apiRouter->buildLink('oauth/token')
            ];
        } elseif ($sessionToken->hasScope(Server::SCOPE_POST)) {
            /** @var Modules $modules */
            $modules = $this->data('Xfrocks\Api:Modules');
            $systemInfo += [
                'api_revision' => 2016062001,
                'api_modules' => $modules->getVersions()
            ];
        }

        $data = [
            'system_info' => $systemInfo,
            'links' => [
                'navigation' => $apiRouter->buildLink('navigation', null, ['parent' => 0]),
                'search' => $apiRouter->buildLink('search'),
                'threads/recent' => $this->buildThreadsLink('threads/recent'),
                'users' => $apiRouter->buildLink('users')
            ],
            'post' => []
        ];

        if ($visitor['user_id'] > 0) {
            $data['links'] += [
                'conversations' => $apiRouter->buildLink('conversations'),
                'forums/followed' => $apiRouter->buildLink('forums/followed'),
                'notifications' => $apiRouter->buildLink('notifications'),
                'threads/followed' => $apiRouter->buildLink('threads/followed'),
                'threads/new' => $this->buildThreadsLink('threads/new'),
                'users/ignored' => $apiRouter->buildLink('users/ignored'),
                'users/me' => $apiRouter->buildLink('users', $visitor)
            ];

            if ($visitor->canPostOnProfile()) {
                $data['post']['status'] = $apiRouter->buildLink('users/me/timeline');
            }
        }

        return $this->api($data);
    }

    protected function buildThreadsLink($link)
    {
        $params = ['data_limit' => $this->options()->discussionsPerPage];
        return $this->app->router('api')->buildLink($link, null, $params);
    }

    protected function getDefaultApiScopeForAction($action)
    {
        return null;
    }
}
