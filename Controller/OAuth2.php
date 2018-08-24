<?php

namespace Xfrocks\Api\Controller;


use OAuth\OAuth2\Token\StdOAuth2Token;
use XF\ConnectedAccount\ProviderData\Facebook;
use XF\Entity\ConnectedAccountProvider;
use XF\Repository\ConnectedAccount;
use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\Util\Crypt;
use Xfrocks\Api\Util\Token;

class OAuth2 extends AbstractController
{
    public function actionGetAuthorize()
    {
        $this->assertCanonicalUrl($this->buildLink('account/authorize'));

        return $this->noPermission();
    }

    public function actionPostToken()
    {
        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');

        return $this->api($apiServer->grantFinalize($this));
    }

    public function actionPostTokenAdmin()
    {
        $params = $this->params()
            ->define('user_id', 'uint', 'User ID');

        $session = $this->session();
        $token = $session->getToken();
        if (empty($token)) {
            return $this->noPermission();
        }

        $this->assertApiScope(Server::SCOPE_MANAGE_SYSTEM);
        if (!\XF::visitor()->hasAdminPermission('user')) {
            return $this->noPermission();
        }

        /** @var Server $apiServer */
        $apiServer = $this->app->container('api.server');
        $scopes = $apiServer->getScopeDefaults();

        $accessToken = $apiServer->newAccessToken($params['user_id'], $token->Client, $scopes);

        return $this->api(Token::transformLibAccessTokenEntity($accessToken));
    }

    public function actionPostTokenFacebook()
    {
        $params = $this
            ->params()
            ->define('client_id', 'str')
            ->define('client_secret', 'str')
            ->define('facebook_token', 'str');

        /** @var Client $client */
        $client = $this->assertRecordExists(
            'Xfrocks\Api:Client',
            $params['client_id'],
            ['User'],
            'bdapi_requested_client_not_found'
        );

        if ($client->client_secret !== $params['client_secret']) {
            return $this->noPermission();
        }

        $provider = $this->assertProviderExists('facebook');
        $handler = $provider->getHandler();

        if (!$handler || !$handler->isUsable($provider)) {
            return $this->noPermission();
        }

        $storageState = $handler->getStorageState($provider, \XF::visitor());

        $tokenObj = new StdOAuth2Token();
        $tokenObj->setAccessToken($params['facebook_token']);

        $storageState->storeToken($tokenObj);

        /** @var Facebook $providerData */
        $providerData = $handler->getProviderData($storageState);
        if (empty($providerData->getProviderKey())) {
            return $this->error(\XF::phrase('bdapi_invalid_facebook_token'), 400);
        }

        $externalProviderKey = sprintf('fb_%s', $providerData->getProviderKey());

        $httpClient = $this->app()->http()->client();
        $fbApp = $httpClient->get('https://graph.facebook.com/app', [
            'query' => [
                'access_token' => $params['facebook_token']
            ]
        ]);

        $fbApp = json_decode($fbApp, true);
        if (!empty($fbApp['id'])
            && $fbApp['id'] === $provider->options['app_id']
        ) {
            $externalProviderKey = $providerData->getProviderKey();
        }

        /** @var ConnectedAccount $connectedAccountRepo */
        $connectedAccountRepo = $this->repository('XF:ConnectedAccount');
        $userConnected = $connectedAccountRepo->getUserConnectedAccountFromProviderData($providerData);
        if ($userConnected && $userConnected->User) {
            return $this->postTokenNonStandard($client, $userConnected->User);
        }

        $userData = [];

        if ($email = $providerData->getEmail()) {
            /** @var \XF\Entity\User|null $userByEmail */
            $userByEmail = $this->em()->find('XF:User', [
                'email' => $email
            ]);

            if ($userByEmail) {
                $userData['associatable'][$userByEmail->user_id] = [
                    'user_id' => $userByEmail->user_id,
                    'username' => $userByEmail->username,
                    'user_email' => $userByEmail->email
                ];
            } else {
                $userData['user_email'] = $email;
            }
        }

        if ($fbUsername = $providerData->getUsername()) {
            $testUser = $this->em()->create('XF:User');
            $testUser->set('username', $fbUsername);

            if (!$testUser->hasErrors()) {
                $userData['username'] = $fbUsername;
            }
        }

        $extraData = [
            'external_provider' => 'facebook',
            'external_provider_key' => $externalProviderKey,
            'access_token' => $params['facebook_token']
        ];

        if (!empty($userData['user_email'])) {
            $extraData['user_email'] = $userData['user_email'];
        }

        $extraData = serialize($extraData);
        $extraTimestamp = intval(time() + $this->app()->options()->bdApi_refreshTokenTTLDays * 86400);

        $userData += [
            'extra_data' => Crypt::encryptTypeOne($extraData, $extraTimestamp),
            'extra_timestamp' => $extraTimestamp
        ];

        $data = [
            'status' => 'ok',
            'message' => \XF::phrase('bdapi_no_facebook_association_found'),
            'user_data' => $userData
        ];

        return $this->api($data);
    }

    /**
     * @param string $username
     * @param string $password
     * @return int|false
     * @throws \XF\Mvc\Reply\Exception
     */
    public function verifyCredentials($username, $password)
    {
        $ip = $this->request->getIp();

        /** @var \XF\Service\User\Login $loginService */
        $loginService = $this->service('XF:User\Login', $username, $ip);
        if ($loginService->isLoginLimited($limitType)) {
            throw $this->errorException(\XF::phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
        }

        $user = $loginService->validate($password, $error);
        if (!$user) {
            return false;
        }

        /** @var \XF\ControllerPlugin\Login $loginPlugin */
        $loginPlugin = $this->plugin('XF:Login');
        if ($loginPlugin->isTfaConfirmationRequired($user)) {
            throw $this->errorException(\XF::phrase('two_step_verification_required'));
        }

        return $user->user_id;
    }

    protected function postTokenNonStandard(Client $client, \XF\Entity\User $user)
    {
        /** @var Server $apiServer */
        $apiServer = $this->app()->container('api.server');
        $scopes = $apiServer->getScopeDefaults();

        $token = $apiServer->newAccessToken(strval($user->user_id), $client, $scopes);

        return $this->api(Token::transformLibAccessTokenEntity($token));
    }

    /**
     * @param string $id
     * @param null $with
     * @param null $phraseKey
     * @return ConnectedAccountProvider
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertProviderExists($id, $with = null, $phraseKey = null)
    {
        /** @var ConnectedAccountProvider $provider */
        $provider = $this->assertRecordExists('XF:ConnectedAccountProvider', $id, $with, $phraseKey);

        return $provider;
    }

    protected function getDefaultApiScopeForAction($action)
    {
        return null;
    }
}
