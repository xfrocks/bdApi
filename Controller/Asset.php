<?php

namespace Xfrocks\Api\Controller;

class Asset extends AbstractController
{
    public function actionGetSdk()
    {
        $params = $this->params()
            ->define('prefix', 'str', 'JS code prefix');

        $prefix = preg_replace('/[^a-zA-Z0-9]/', '', $params['prefix']);

        if (\XF::$debugMode) {
            $sdkPath = dirname(__DIR__) . '/_files/js/Xfrocks/Api/sdk.js';
        } else {
            $sdkPath = sprintf(
                '%1$s%2$sjs%2$sXfrocks%2$sApi%2$s' . 'sdk.min.js',
                \XF::getRootDirectory(),
                DIRECTORY_SEPARATOR
            );
        }

        if (!file_exists($sdkPath)) {
            return $this->noPermission();
        }
        $sdk = strval(file_get_contents($sdkPath));
        $sdk = str_replace('{prefix}', $prefix, $sdk);
        $sdk = str_replace('{data_uri}', $this->app->router('public')->buildLink('misc/api-data'), $sdk);
        $sdk = str_replace('{request_uri}', $this->buildApiLink('index'), $sdk);

        $ttl = 31536000;
        $expires = \XF::$time + $ttl;
        header('Content-Type: application/x-javascript; charset=utf-8', true);
        header('Cache-Control: public, max-age=' . $ttl, true);
        header(sprintf('Last-Modified: %s', gmstrftime("%a, %d %b %Y %T %Z", filemtime($sdkPath) ?: 0)), true);
        header(sprintf('Expires: %s', gmstrftime("%a, %d %b %Y %T %Z", $expires)), true);
        die($sdk);
    }

    public function assertBoardActive($action)
    {
        // intentionally left empty
    }

    public function assertViewingPermissions($action)
    {
        // intentionally left empty
    }

    protected function getDefaultApiScopeForAction($action)
    {
        return false;
    }
}
