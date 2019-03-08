<?php

namespace Xfrocks\Api\XF\Pub\Controller;

use XF\Mvc\Entity\Finder;
use XF\Util\Random;
use Xfrocks\Api\ControllerPlugin\Login;
use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\OAuth2\Server;

class Account extends XFCP_Account
{
    public function actionApi()
    {
        $visitor = \XF::visitor();

        $clients = $this->getApiClientRepo()->findUserClients($visitor->user_id)
            ->with(['User'])
            ->fetch();

        $viewParams = [
            'canAddClient' => $visitor->hasPermission('general', 'bdApi_clientNew'),
            'clients' => $clients
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\Index', 'bdapi_account_api', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    public function actionApiClientAdd()
    {
        $viewParams = [
            'client' => $this->em()->create('Xfrocks\Api:Client')
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\Edit', 'bdapi_account_api_client_add', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    /**
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\PrintableException
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionApiClientDelete()
    {
        $client = $this->assertEditableApiClient($this->filter('client_id', 'str'));

        if ($this->isPost()) {
            $client->delete();

            return $this->redirect($this->buildLink('account/api'));
        }

        $viewParams = [
            'client' => $client
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\Delete', 'bdapi_account_api_client_delete', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    /**
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionApiClientEdit()
    {
        $client = $this->assertEditableApiClient($this->filter('client_id', 'str'));

        $viewParams = [
            'client' => $client
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\Edit', 'bdapi_account_api_client_edit', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    /**
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionApiClientSave()
    {
        $this->assertPostOnly();

        $clientId = $this->filter('client_id', 'str');

        if (!empty($clientId)) {
            $client = $this->assertEditableApiClient($clientId);
        } else {
            /** @var Client $client */
            $client = $this->em()->create('Xfrocks\Api:Client');
        }

        $client->bulkSet($this->filter([
            'name' => 'str',
            'description' => 'str',
            'redirect_uri' => 'str'
        ]));

        $options = $this->filter('options', 'array');
        $client->setClientOptions($this->filterArray(
            $options,
            [
                'whitelisted_domains' => 'str'
            ]
        ));

        $client->save();

        return $this->redirect($this->buildLink('account/api'));
    }

    /**
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\PrintableException
     */
    public function actionApiLogout()
    {
        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('Xfrocks\Api:Login');
        return $loginPlugin->logout();
    }

    /**
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \League\OAuth2\Server\Exception\InvalidGrantException
     * @throws \League\OAuth2\Server\Exception\UnsupportedResponseTypeException
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionAuthorize()
    {
        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');

        $clientId = $this->filter('client_id', 'str');
        $clientIsAuto = false;
        if (empty($clientId)) {
            /** @var Client $visitorClient */
            $visitorClient = $this->getApiClientRepo()->findUserClients(\XF::visitor()->user_id)
                ->order(Finder::ORDER_RANDOM)
                ->fetchOne();
            if (!empty($visitorClient)) {
                $clientIsAuto = true;
                $apiServer->setRequestQuery('client_id', $visitorClient->client_id);
                $apiServer->setRequestQuery('redirect_uri', $visitorClient->redirect_uri);
                $apiServer->setRequestQuery('response_type', 'token');
                $apiServer->setRequestQuery('scope', Server::SCOPE_READ);
                $apiServer->setRequestQuery('state', Random::getRandomString(32));
            }
        }

        $linkParams = $authorizeParams = $apiServer->grantAuthCodeCheckParams($this);

        /** @var Client $client */
        $client = $linkParams['client'];
        unset($linkParams['client']);
        $linkParams['client_id'] = $client->client_id;

        $scopeIds = $linkParams['scopes'];
        unset($linkParams['scopes']);

        $needAuthScopes = [];
        $requestedScopes = [];
        $userScopes = $this->finder('Xfrocks\Api:UserScope')
            ->where('client_id', $client->client_id)
            ->where('user_id', \XF::visitor()->user_id)
            ->keyedBy('scope')
            ->fetch();
        foreach ($scopeIds as $scopeId) {
            $requestedScopes[$scopeId] = $apiServer->getScopeDescription($scopeId);

            // TODO: auto authorize scopes

            if (!isset($userScopes[$scopeId])) {
                $needAuthScopes[$scopeId] = $requestedScopes[$scopeId];
            }
        }

        $bypassConfirmation = false;
        if (count($requestedScopes) > 0 && count($needAuthScopes) === 0) {
            $bypassConfirmation = true;
        }
        if ($clientIsAuto) {
            $bypassConfirmation = false;
        }

        if ($this->isPost() || $bypassConfirmation) {
            $userScopeKeys = $userScopes->keys();
            if ($this->isPost()) {
                $authorizeParams['scopes'] = $this->filter('scopes', 'array-str');
            }
            $authorizeParams['scopes'] = array_merge($authorizeParams['scopes'], $userScopeKeys);
            $authorizeParams['scopes'] = array_unique($authorizeParams['scopes']);

            return $apiServer->grantAuthCodeNewAuthRequest($this, $authorizeParams);
        }

        $viewParams = [
            'client' => $client,
            'needAuthScopes' => $needAuthScopes,

            'clientIsAuto' => $clientIsAuto,
            'linkParams' => $linkParams,
        ];

        $view = $this->view('Xfrocks\Api:Account\Authorize', 'bdapi_account_authorize', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    /**
     * @param string $clientId
     * @return Client
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertEditableApiClient($clientId)
    {
        /** @var Client $client */
        $client = $this->assertRecordExists('Xfrocks\Api:Client', $clientId);

        if (!$client->canEdit()) {
            throw $this->exception($this->notFound(\XF::phrase('bdapi_requested_client_not_found')));
        }

        return $client;
    }

    /**
     * @return \Xfrocks\Api\Repository\Client
     */
    protected function getApiClientRepo()
    {
        /** @var \Xfrocks\Api\Repository\Client $clientRepo */
        $clientRepo = $this->repository('Xfrocks\Api:Client');
        return $clientRepo;
    }
}
