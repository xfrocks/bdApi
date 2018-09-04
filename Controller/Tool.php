<?php

namespace Xfrocks\Api\Controller;

use Xfrocks\Api\ControllerPlugin\Login;
use Xfrocks\Api\Util\Crypt;
use Xfrocks\Api\Util\OneTimeToken;

class Tool extends AbstractController
{
    public function actionGetLogin()
    {
        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('Xfrocks\Api:Login');
        return $loginPlugin->initiate('misc/api-login');
    }

    public function actionGetLogout()
    {
        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('Xfrocks\Api:Login');
        return $loginPlugin->initiate('account/api-logout');
    }

    public function actionGetWebsubEchoHubChallenge()
    {
        if (!\XF::$debugMode) {
            return $this->noPermission();
        }

        $params = $this->params()
            ->define('hub_challenge', 'str');

        die($params['hub_challenge']);
    }

    public function actionGetWebsubEchoNone()
    {
        exit(0);
    }

    public function actionPostLink()
    {
        $params = $this->params()
            ->define('type', 'str', 'Link type (admin, api, or public)', 'public')
            ->define('route', 'str', 'Link route', 'index');

        switch ($params['type']) {
            case 'admin':
            case 'public':
                $link = $this->app->router($params['type'])->buildLink($params['route']);
                break;
            case 'api':
            default:
                $link = $this->router()->buildLink($params['route']);
                break;
        }

        return $this->api(['link' => $link]);
    }

    public function actionPostOtt()
    {
        $params = $this->params()
            ->define('ttl', 'uint', 'Time to live in seconds');

        if (!\XF::$debugMode) {
            return $this->noPermission();
        }

        $session = $this->session();
        $token = $session->getToken();
        if ($token === null) {
            return $this->noPermission();
        }
        $client = $token->Client;

        return $this->api(['ott' => OneTimeToken::generate($params['ttl'], $client)]);
    }

    public function actionPostPasswordTest()
    {
        $params = $this->params()
            ->define('password', 'str')
            ->define('password_algo', 'str')
            ->define('decrypt', 'bool');

        if (!\XF::$debugMode) {
            return $this->noPermission();
        }

        if (!$params['decrypt']) {
            $result = Crypt::encrypt($params['password'], $params['password_algo']);
        } else {
            $result = Crypt::decrypt($params['password'], $params['password_algo']);
        }

        return $this->api(['result' => $result]);
    }

    public function actionPostWebsubEchoHubChallenge()
    {
        if (!\XF::$debugMode) {
            return $this->noPermission();
        }

        $inputRaw = $this->request->getInputRaw();
        \XF\Util\File::log(__METHOD__, $inputRaw);

        exit(0);
    }

    protected function getDefaultApiScopeForAction($action)
    {
        return false;
    }
}
