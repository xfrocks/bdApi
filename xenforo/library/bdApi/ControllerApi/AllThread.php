<?php

class bdApi_ControllerApi_AllThread extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        return $this->responseReroute('bdApi_ControllerApi_Thread', 'get-index');
    }

}
