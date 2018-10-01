<?php

namespace Xfrocks\Api\XFMG\Controller;


use XF\Mvc\ParameterBag;
use Xfrocks\Api\Controller\AbstractController;

class Album extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        return $this->api(['hello' => 'world']);
    }
}
