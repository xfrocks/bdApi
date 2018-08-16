<?php

namespace Xfrocks\Api\Controller;

use Xfrocks\Api\Data\Param;
use Xfrocks\Api\Util\Crypt;
use Xfrocks\Api\Util\OneTimeToken;

class Tool extends AbstractController
{
    public function actionPostLink()
    {
        $paramType = new Param('str', 'Link type (admin, api, or public)');
        $paramType->options['default'] = 'public';
        $paramRoute = new Param('str', 'Link route');
        $paramRoute->options['default'] = 'index';
        $params = $this->params()
            ->define('type', $paramType)
            ->define('route', $paramRoute);

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
}
