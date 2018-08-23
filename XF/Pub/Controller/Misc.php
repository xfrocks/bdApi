<?php

namespace Xfrocks\Api\XF\Pub\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\ControllerPlugin\Login;
use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\Server;

class Misc extends XFCP_Misc
{
    public function actionApiData()
    {
        $callback = $this->filter('callback', 'str');
        $clientId = $this->filter('client_id', 'str');
        $cmd = $this->filter('cmd', 'str');
        $scope = $this->filter('scope', 'str');

        $data = [$cmd => 0];

        /** @var Client $client */
        $client = $this->assertRecordExists('Xfrocks\Api:Client', $clientId);
        $visitor = \XF::visitor();

        switch ($cmd) {
            case 'authorized':
                if ($scope === '') {
                    // no scope requested, check for scope `read`
                    $requestedScopes = [Server::SCOPE_READ];
                } else {
                    $requestedScopes = explode(Listener::$scopeDelimiter, $scope) ?: [];
                }
                $requestedScopesAccepted = [];

                // TODO: check for auto authorize

                if ($data[$cmd] === 0) {
                    $userScopes = $this->finder('Xfrocks\Api:UserScope')
                        ->where('client_id', $client->client_id)
                        ->where('user_id', $visitor->user_id)
                        ->keyedBy('scope')
                        ->fetch();

                    foreach ($requestedScopes as $requestedScope) {
                        if (isset($userScopes[$requestedScope])) {
                            $requestedScopesAccepted[] = $requestedScope;
                        }
                    }

                    if (count($requestedScopes) === count($requestedScopesAccepted)) {
                        $data[$cmd] = 1;
                    }
                }

                if ($data[$cmd] > 0) {
                    if (!empty($scope)) {
                        $data += $this->prepareApiDataForVisitor($visitor, $requestedScopesAccepted);
                    } else {
                        // just checking for connection status, return user_id only
                        $data['user_id'] = $visitor->user_id;
                    }
                }

                // switch ($cmd)
                break;
        }

        $this->signApiData($client, $data);

        $viewParams = [
            'callback' => $callback,
            'client_id' => $clientId,
            'cmd' => $cmd,
            'data' => $data,
        ];

        $this->setResponseType('raw');

        return $this->view('Xfrocks\Api:Misc\ApiData', '', $viewParams);
    }

    public function actionApiLogin()
    {
        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('Xfrocks\Api:Login');
        return $loginPlugin->login('misc/api-login');
    }

    public function checkCsrfIfNeeded($action, ParameterBag $params)
    {
        if ($action === 'ApiData') {
            return;
        }

        parent::checkCsrfIfNeeded($action, $params);
    }

    /**
     * @param \XF\Entity\User $visitor
     * @param string[] $scopes
     * @return array
     */
    protected function prepareApiDataForVisitor($visitor, array $scopes)
    {
        $data = [
            'user_id' => $visitor->user_id,
            'username' => $visitor->username,
            'user_unread_notification_count' => $visitor->alerts_unread
        ];

        if (in_array(Server::SCOPE_PARTICIPATE_IN_CONVERSATIONS, $scopes, true)) {
            $data['user_unread_conversation_count'] = $visitor->conversations_unread;
        }

        return $data;
    }

    /**
     * @param Client $client
     * @param array $data
     */
    protected function signApiData($client, array &$data)
    {
        $str = '';

        $keys = array_keys($data);
        asort($keys);
        foreach ($keys as $key) {
            if ($key === 'signature') {
                // do not include existing signature when signing
                // it's safe to run this method more than once with the same $data
                continue;
            }

            if (is_array($data[$key])) {
                // do not support array in signing for now
                unset($data[$key]);
                continue;
            }

            if (is_bool($data[$key])) {
                // strval(true) = 1 while strval(false) = 0
                // so we will normalize bool to int before the strval
                $data[$key] = ($data[$key] ? 1 : 0);
            }

            $str .= sprintf('%s=%s&', $key, $data[$key]);
        }

        if (\XF::$debugMode && !headers_sent()) {
            header('X-Api-Signature-String-Without-Secret: ' . $str);
        }
        $str .= $client->client_secret;

        $data['signature'] = md5($str);
    }
}
