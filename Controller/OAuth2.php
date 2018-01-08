<?php

namespace Xfrocks\Api\Controller;

use Xfrocks\Api\OAuth2\Server;

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

    /**
     * @param string $username
     * @param string $password
     * @return int
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
            return 0;
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
