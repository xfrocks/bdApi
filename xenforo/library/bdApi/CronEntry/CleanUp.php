<?php

class bdApi_CronEntry_CleanUp
{
    public static function pruneExpired()
    {
        /* @var $oauth2Model bdApi_Model_OAuth2 */
        $oauth2Model = XenForo_Model::create('bdApi_Model_OAuth2');

        $oauth2Model->getAuthCodeModel()->pruneExpired();
        $oauth2Model->getRefreshTokenModel()->pruneExpired();
        $oauth2Model->getTokenModel()->pruneExpired();

        $oauth2Model->getLogModel()->pruneExpired();
    }
}
