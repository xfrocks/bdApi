<?php

namespace Xfrocks\Api\ControllerPlugin;

use XF\ControllerPlugin\AbstractPlugin;
use Xfrocks\Api\Controller\AbstractController;
use Xfrocks\Api\Util\Crypt;

class Login extends AbstractPlugin
{
    const INITIATE_TTL = 10;
    const TWO_STEP_TTL = 300;

    public function initiate($publicLink)
    {
        /** @var AbstractController $apiController */
        $apiController = $this->controller;
        $params = $apiController->params()
            ->define('redirect_uri', 'str', 'URI to redirect afterwards');

        if (empty($params['redirect_uri'])) {
            return $this->noPermission();
        }

        $session = $apiController->session();
        $token = $session->getToken();
        if (empty($token)) {
            return $this->noPermission();
        }

        $client = $token->Client;
        if (empty($client) || !$client->isValidRedirectUri($params['redirect_uri'])) {
            return $this->noPermission();
        }

        $userId = \XF::visitor()->user_id;
        if (empty($userId)) {
            return $this->noPermission();
        }

        $timestamp = time() + self::INITIATE_TTL;
        $linkParams = array(
            '_xfRedirect' => Crypt::encryptTypeOne($params['redirect_uri'], $timestamp),
            'timestamp' => $timestamp,
            'user_id' => Crypt::encryptTypeOne(strval($userId), $timestamp)
        );
        $redirectTarget = $this->app->router('public')->buildLink($publicLink, null, $linkParams);

        return $this->redirectSilently($redirectTarget);
    }

    public function login($selfLink)
    {
        $params = $this->filter([
            '_xfRedirect' => 'str',
            'timestamp' => 'uint',
            'user_id' => 'str'
        ]);

        $redirect = Crypt::decryptTypeOne($params['_xfRedirect'], $params['timestamp']);
        $userId = Crypt::decryptTypeOne($params['user_id'], $params['timestamp']);
        /** @var \XF\Entity\User $user */
        $user = $this->assertRecordExists('XF:User', $userId);
        if ($user->user_id === \XF::visitor()->user_id) {
            return $this->redirectSilently($redirect);
        }

        /** @var \XF\ControllerPlugin\Login $loginPlugin */
        $loginPlugin = $this->plugin('XF:Login');
        $loginPlugin->triggerIfTfaConfirmationRequired(
            $user,
            function () use ($selfLink, $redirect, $userId) {
                $timestamp = time() + self::TWO_STEP_TTL;
                $comebackParams = array(
                    '_xfRedirect' => Crypt::encryptTypeOne($redirect, $timestamp),
                    'timestamp' => $timestamp,
                    'user_id' => Crypt::encryptTypeOne($userId, $timestamp)
                );
                $comebackLink = $this->buildLink($selfLink, null, $comebackParams);

                $twoStepParams = ['_xfRedirect' => $comebackLink, 'remember' => 1];
                $twoStepLink = $this->buildLink('login/two-step', null, $twoStepParams);

                throw $this->exception($this->redirectSilently($twoStepLink));
            }
        );
        $loginPlugin->completeLogin($user, true);

        return $this->redirectSilently($redirect);
    }

    public function logout()
    {
        $params = $this->filter([
            '_xfRedirect' => 'str',
            'timestamp' => 'uint',
            'user_id' => 'str'
        ]);

        $redirect = Crypt::decryptTypeOne($params['_xfRedirect'], $params['timestamp']);
        $userId = intval(Crypt::decryptTypeOne($params['user_id'], $params['timestamp']));
        if ($userId !== \XF::visitor()->user_id) {
            return $this->redirectSilently($this->buildLink('index'));
        }

        /** @var \XF\ControllerPlugin\Login $loginPlugin */
        $loginPlugin = $this->plugin('XF:Login');
        $loginPlugin->logoutVisitor();

        return $this->redirectSilently($redirect);
    }

    /**
     * @param string $url
     * @return \XF\Mvc\Reply\Redirect
     */
    public function redirectSilently($url)
    {
        return $this->redirect($url, '', 'permanent');
    }
}
