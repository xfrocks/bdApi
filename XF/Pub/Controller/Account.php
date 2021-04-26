<?php

namespace Xfrocks\Api\XF\Pub\Controller;

use XF\Entity\UserConnectedAccount;
use XF\Mvc\Entity\Finder;
use XF\Util\Random;
use Xfrocks\Api\ControllerPlugin\Login;
use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\Entity\Token;
use Xfrocks\Api\Entity\UserScope;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Repository\AuthCode;
use Xfrocks\Api\Repository\RefreshToken;
use Xfrocks\Api\Repository\Subscription;
use Xfrocks\Api\XF\Entity\User;

class Account extends XFCP_Account
{
    /**
     * @return \XF\Mvc\Reply\View
     */
    public function actionApi()
    {
        $visitor = \XF::visitor();

        $clients = $this->getApiClientRepo()->findUserClients($visitor->user_id)
            ->with(['User'])
            ->fetch();

        $userScopes = $this->finder('Xfrocks\Api:UserScope')
            ->with('Client', true)
            ->where('user_id', $visitor->user_id)
            ->order('accept_date', 'DESC')
            ->fetch();

        $tokens = $this->finder('Xfrocks\Api:Token')
            ->where('user_id', $visitor->user_id)
            ->fetch();

        $userScopesByClientIds = [];
        $scopesPhrase = [];
        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');

        /** @var UserScope $userScope */
        foreach ($userScopes as $userScope) {
            if (!isset($userScopesByClientIds[$userScope->client_id])) {
                $userScopesByClientIds[$userScope->client_id] = [
                    'last_issue_date' => 0,
                    'user_scopes' => [],
                    'client' => $userScope->Client,
                ];
            }

            $userScopesByClientIds[$userScope->client_id]['last_issue_date'] = \max(
                $userScopesByClientIds[$userScope->client_id]['last_issue_date'],
                $userScope->accept_date
            );
            $userScopesByClientIds[$userScope->client_id]['user_scopes'][$userScope->scope] = $userScope;
            $scopesPhrase[$userScope->scope] = $apiServer->getScopeDescription($userScope->scope);
        }

        /** @var Token $token */
        foreach ($tokens as $token) {
            if (!isset($userScopesByClientIds[$token->client_id])) {
                continue;
            }

            $userScopesByClientIds[$token->client_id]['last_issue_date'] = \max(
                $userScopesByClientIds[$token->client_id]['last_issue_date'],
                $token->issue_date
            );
        }

        $viewParams = [
            'clients' => $clients,
            'userScopesClientIds' => $userScopesByClientIds,
            'scopesPhrase' => $scopesPhrase,
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\Index', 'bdapi_account_api', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    public function actionApiUpdateScope()
    {
        $visitor = \XF::visitor();
        $clientId = $this->filter('client_id', 'str');
        /** @var Client $client */
        $client = $this->assertRecordExists('Xfrocks\Api:Client', $clientId);

        /** @var \Xfrocks\Api\Repository\UserScope $userScopeRepo */
        $userScopeRepo = $this->repository('Xfrocks\Api:UserScope');
        $userScopes = $this->finder('Xfrocks\Api:UserScope')
            ->where('client_id', $client->client_id)
            ->where('user_id', $visitor->user_id)
            ->fetch();

        if ($userScopes->count() === 0) {
            return $this->noPermission();
        }

        if ($this->isPost()) {
            $isRevoke = $this->filter('revoke', 'bool') === true;
            $scopes = $this->filter('scopes', 'array-str');
            if (count($scopes) === 0) {
                $isRevoke = true;
            }

            $db = $this->app()->db();
            $db->beginTransaction();

            try {
                $userScopesChanged = false;
                /** @var UserScope $userScope */
                foreach ($userScopes as $userScope) {
                    if ($isRevoke || !in_array($userScope->scope, $scopes, true)) {
                        $userScopeRepo->deleteUserScope($client->client_id, $visitor->user_id, $userScope->scope);
                        $userScopesChanged = true;
                    }
                }

                if ($userScopesChanged) {
                    /** @var AuthCode $authCodeRepo */
                    $authCodeRepo = $this->repository('Xfrocks\Api:AuthCode');
                    $authCodeRepo->deleteAuthCodes($client->client_id, $visitor->user_id);
                    /** @var RefreshToken $refreshTokenRepo */
                    $refreshTokenRepo = $this->repository('Xfrocks\Api:RefreshToken');
                    $refreshTokenRepo->deleteRefreshTokens($client->client_id, $visitor->user_id);
                    /** @var \Xfrocks\Api\Repository\Token $tokenRepo */
                    $tokenRepo = $this->repository('Xfrocks\Api:Token');
                    $tokenRepo->deleteTokens($client->client_id, $visitor->user_id);
                }

                if ($isRevoke) {
                    /** @var Subscription $subscriptionRepo */
                    $subscriptionRepo = $this->repository('Xfrocks\Api:Subscription');
                    $subscriptionRepo->deleteSubscriptions(
                        $client->client_id,
                        Subscription::TYPE_USER,
                        $visitor->user_id
                    );

                    $subscriptionRepo->deleteSubscriptions(
                        $client->client_id,
                        Subscription::TYPE_NOTIFICATION,
                        $visitor->user_id
                    );
                }

                $connectedAccounts = $this->finder('XF:UserConnectedAccount')
                    ->where('user_id', $visitor->user_id)
                    ->where('provider', 'api_' . $client->client_id)
                    ->fetch();
                /** @var UserConnectedAccount $connectedAccount */
                foreach ($connectedAccounts as $connectedAccount) {
                    $connectedAccount->delete(true, false);
                }

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();

                throw $e;
            }

            return $this->redirect($this->buildLink('account/api'));
        }

        $scopesPhrase = [];
        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');

        foreach ($userScopes as $userScope) {
            $scopesPhrase[$userScope->scope] = $apiServer->getScopeDescription($userScope->scope);
        }

        $viewParams = [
            'client' => $client,
            'userScopes' => $userScopes,
            'scopesPhrase' => $scopesPhrase,
        ];

        $view = $this->view('Xfrocks\Api:Account\Api\UpdateScope', 'bdapi_account_api_update_scope', $viewParams);
        return $this->addAccountWrapperParams($view, 'api');
    }

    /**
     * @return \XF\Mvc\Reply\View
     */
    public function actionApiClientAdd()
    {
        /** @var User $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canAddApiClient()) {
            return $this->noPermission();
        }

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

        if ($clientId !== '') {
            $client = $this->assertEditableApiClient($clientId);
        } else {
            /** @var User $visitor */
            $visitor = \XF::visitor();
            if (!$visitor->canAddApiClient()) {
                return $this->noPermission();
            }

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
        if ($clientId === '') {
            /** @var Client|null $visitorClient */
            $visitorClient = $this->getApiClientRepo()->findUserClients(\XF::visitor()->user_id)
                ->order(Finder::ORDER_RANDOM)
                ->fetchOne();
            if ($visitorClient !== null) {
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
