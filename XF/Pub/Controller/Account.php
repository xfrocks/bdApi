<?php

namespace Xfrocks\Api\XF\Pub\Controller;

use XF\Mvc\Entity\Finder;
use XF\Util\Random;
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
            'client' => $this->getApiClientRepo()->newClient()
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\Edit', 'bdapi_account_api_client_add', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

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

    public function actionApiClientEdit()
    {
        $client = $this->assertEditableApiClient($this->filter('client_id', 'str'));

        $viewParams = [
            'client' => $client
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\Edit', 'bdapi_account_api_client_edit', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    public function actionApiClientSave()
    {
        $this->assertPostOnly();

        $clientRepo = $this->getApiClientRepo();
        $clientId = $this->filter('client_id', 'str');

        if (!empty($clientId)) {
            $client = $this->assertEditableApiClient($clientId);
        } else {
            $client = $clientRepo->newClient([
                'client_id' => $clientRepo->generateClientId(),
                'client_secret' => $clientRepo->generateClientSecret(),
                'user_id' => \XF::visitor()->user_id
            ]);
        }

        $client->bulkSet($this->filter([
            'name' => 'str',
            'description' => 'str',
            'redirect_uri' => 'str'
        ]));

        $client->setClientOptions($this->filter(
            'options',
            [
                'whitelisted_domains' => 'str'
            ]
        ));

        $client->save();

        return $this->redirect($this->buildLink('account/api'));
    }

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
        foreach ($scopeIds as $scopeId) {
            $needAuthScopes[$scopeId] = $apiServer->getScopeDescription($scopeId);
        }

        if ($this->isPost()) {
            $authorizeParams['scopes'] = $this->filter('scopes', 'array-str');

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

    protected function assertEditableApiClient($clientId)
    {
        /** @var Client $client */
        $client = $this->assertRecordExists('Xfrocks\Api:Client', $clientId);

        if (!$client->canEdit()) {
            return $this->exception($this->notFound(\XF::phrase('bdapi_requested_client_not_found')));
        }

        return $client;
    }

    /**
     * @return \Xfrocks\Api\Repository\Client
     */
    protected function getApiClientRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('Xfrocks\Api:Client');
    }
}
