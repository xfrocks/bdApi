<?php

namespace Xfrocks\Api\Controller;

use Xfrocks\Api\Entity\Client;
use Xfrocks\Api\OAuth2\Server;
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

    protected function getDefaultApiScopeForAction($action)
    {
        return null;
    }
}
