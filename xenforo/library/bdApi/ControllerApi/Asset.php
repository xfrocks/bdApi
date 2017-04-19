<?php

class bdApi_ControllerApi_Asset extends bdApi_ControllerApi_Abstract
{
    public function actionGetSdk()
    {
        $prefix = $this->_input->filterSingle('prefix', XenForo_Input::STRING);
        $prefix = preg_replace('/[^a-zA-Z0-9]/', '', $prefix);

        $sdkPath = XenForo_Autoloader::getInstance()->getRootDir() . '/../js/bdApi/full/sdk.js';
        $sdk = file_get_contents($sdkPath);
        $sdk = str_replace('{prefix}', $prefix, $sdk);
        $sdk = str_replace('{data_uri}', XenForo_Link::buildPublicLink('canonical:misc/api-data'), $sdk);
        $sdk = str_replace('{request_uri}', bdApi_Data_Helper_Core::safeBuildApiLink('index'), $sdk);

        header('Content-Type: application/x-javascript; charset=utf-8');
        header('Cache-Control: public, max-age=31536000');
        header(sprintf('Last-Modified: %s', gmstrftime("%a, %d %b %Y %T %Z", filemtime($sdkPath))));
        die($sdk);
    }

    protected function _getScopeForAction($action)
    {
        return false;
    }

    protected function _assertViewingPermissions($action)
    {
    }

    protected function _assertBoardActive($action)
    {
    }
}
