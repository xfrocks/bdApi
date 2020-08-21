<?php

namespace Xfrocks\Api\Controller;

use Xfrocks\Api\ControllerPlugin\Login;
use Xfrocks\Api\ControllerPlugin\ParseLink;
use Xfrocks\Api\Util\Crypt;
use Xfrocks\Api\Util\OneTimeToken;

class Tool extends AbstractController
{
    /**
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Redirect
     * @throws \XF\PrintableException
     */
    public function actionGetChr()
    {
        $params = $this->params()
            ->define('html', 'str')
            ->define('required_externals', 'str')
            ->define('timestamp', 'int');
        $link = $this->buildLink('misc/api-chr', null, [
            'html' => $params['html'],
            'required_externals' => $params['required_externals'],
            'timestamp' => $params['timestamp'],
        ]);

        if (\XF::visitor()->user_id > 0) {
            /** @var Login $loginPlugin */
            $loginPlugin = $this->plugin('Xfrocks\Api:Login');
            return $loginPlugin->initiate('misc/api-login', $link);
        }

        return $this->redirect($link, '', 'permanent');
    }

    /**
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Redirect
     * @throws \XF\PrintableException
     */
    public function actionGetLogin()
    {
        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('Xfrocks\Api:Login');
        return $loginPlugin->initiate('misc/api-login');
    }

    /**
     * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Redirect
     * @throws \XF\PrintableException
     */
    public function actionGetLogout()
    {
        /** @var Login $loginPlugin */
        $loginPlugin = $this->plugin('Xfrocks\Api:Login');
        return $loginPlugin->initiate('account/api-logout');
    }

    /**
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionGetWebsubEchoHubChallenge()
    {
        if (!\XF::$debugMode) {
            return $this->noPermission();
        }

        $params = $this->params()
            ->define('hub_challenge', 'str');

        die($params['hub_challenge']);
    }

    /**
     * @return void
     */
    public function actionGetWebsubEchoNone()
    {
        exit(0);
    }

    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\PrintableException
     */
    public function actionPostCrypt()
    {
        $params = $this->params()
            ->define('algo', 'str', 'Encryption algorithm', Crypt::ALGO_AES_128)
            ->define('data', 'str', 'Data string to be encrypted', 'data')
            ->define('data_encrypted', 'str', 'Data string to be decrypted')
            ->define('key', 'str', 'Key to encrypt/decrypt', 'key');

        $data = [
            'algo' => $params['algo'],
            'key' => $params['key'],
            'results' => [
                'encrypt' => [
                    'input' => $params['data'],
                    'output' => Crypt::encrypt($params['data'], $params['algo'], $params['key'])
                ],
            ],
        ];

        $dataEncrypted = $params['data_encrypted'];
        if (strlen($dataEncrypted) > 0) {
            $data['results']['decrypt'] = [
                'input' => $dataEncrypted,
                'output' => Crypt::decrypt($dataEncrypted, $params['algo'], $params['key'])
            ];
        }

        return $this->api($data);
    }

    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
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
                $link = $this->buildApiLink($params['route']);
                break;
        }

        return $this->api(['link' => $link]);
    }

    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     */
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
        if ($client === null) {
            return $this->noPermission();
        }

        return $this->api(['ott' => OneTimeToken::generate($params['ttl'], $client)]);
    }

    /**
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\PrintableException
     */
    public function actionPostPasswordTest()
    {
        $params = $this->params()
            ->define('password', 'str')
            ->define('password_algo', 'str')
            ->define('decrypt', 'bool');

        if (!\XF::$debugMode) {
            return $this->noPermission();
        }

        if ($params['decrypt'] === false) {
            $result = Crypt::encrypt($params['password'], $params['password_algo']);
        } else {
            $result = Crypt::decrypt($params['password'], $params['password_algo']);
        }

        return $this->api(['result' => $result]);
    }

    /**
     * @return \XF\Mvc\Reply\AbstractReply
     * @throws \Exception
     */
    public function actionGetParseLink()
    {
        $params = $this->params()
            ->define('link', 'str');

        /** @var ParseLink $plugin */
        $plugin = $this->plugin('Xfrocks\Api:ParseLink');
        return $plugin->parse($params['link']);
    }

    /**
     * @return \XF\Mvc\Reply\AbstractReply
     */
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
        return null;
    }
}
