<?php

abstract class XenForo_Link
{
    const API_LINK_GROUP = '';

    public function __construct($linkString, $canPrependFull = true)
    {
        // do nothing
    }

    public static function buildApiLink(/** @noinspection PhpUnusedParameterInspection */
        $type, $data = null, array $extraParams = array(), $skipPrepend = false)
    {
        return '';
    }

    public static function convertApiUriToAbsoluteUri(/** @noinspection PhpUnusedParameterInspection */
        $uri, $includeHost = false, array $paths = null)
    {
        return '';
    }
}

class _XenForo_Link extends XenForo_Link
{
    public static function buildPublicLink($type, $data = null, array $extraParams = array(), $skipPrepend = false)
    {
        return '';
    }

    public static function convertUriToAbsoluteUri($uri, $includeHost = false, array $paths = null)
    {
        return '';
    }

    protected static function _checkForFullLink($type, &$fullLink, &$fullLinkPrefix)
    {
        return true;
    }

}