<?php

namespace Xfrocks\Api\Controller;

use Xfrocks\Api\Util\Crypt;

class Tool extends AbstractController
{
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
