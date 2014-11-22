<?php

/* @var $app XenForo_Application */
$app = XenForo_Application::getInstance();
$xenforoLinkPath = $app->getRootDir() . '/library/XenForo/Link.php';
$xenforoLinkContents = file_get_contents($xenforoLinkPath);

// remove <?php
$xenforoLinkContents = substr($xenforoLinkContents, 5);

// rename class
$xenforoLinkContents = str_replace('class XenForo_Link', 'class _XenForo_Link', $xenforoLinkContents);

// change self reference to XenForo, required to patch https issue
$xenforoLinkContents = str_replace('self::', 'XenForo_Link::', $xenforoLinkContents);

eval($xenforoLinkContents);

class bdApi_Link extends _XenForo_Link
{
    const API_LINK_GROUP = 'api';

    public static function buildPublicLink($type, $data = null, array $extraParams = array(), $skipPrepend = false)
    {
        // the type MUST BE canonical:$type
        // NOTE: this is the opposite with api links
        if (strpos($type, 'full:') === 0) {
            // replace full: with canonical:
            $type = str_replace('full:', 'canonical:', $type);
        } elseif (strpos($type, 'canonical:') === false) {
            // enforce canonical:
            $type = 'canonical:' . $type;
        }

        return parent::buildPublicLink($type, $data, $extraParams, $skipPrepend);
    }

    public static function buildApiLink($type, $data = null, array $extraParams = array(), $skipPrepend = false)
    {
        // the type MUST BE full:type
        // NOTE: this is the opposite with public links
        if (strpos($type, 'canonical:') === 0) {
            // replace canonical: with full:
            $type = str_replace('canonical:', 'full:', $type);
        } elseif (strpos($type, 'full:') === false) {
            // enforce full:
            $type = 'full:' . $type;
        }

        // auto appends oauth_token param from the session
        if (!isset($extraParams[OAUTH2_TOKEN_PARAM_NAME])) {
            $session = XenForo_Application::get('session');
            $oauthToken = $session->getOAuthTokenText();
            if (!empty($oauthToken)) {
                $extraParams[OAUTH2_TOKEN_PARAM_NAME] = $oauthToken;
            }
        }

        $type = XenForo_Link::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

        $link = XenForo_Link::_buildLink(self::API_LINK_GROUP, $type, $data, $extraParams, $prefix);
        $queryString = XenForo_Link::buildQueryString($extraParams);

        if ($link instanceof XenForo_Link) {
            $isRaw = true;
            $canPrependFull = $link->canPrependFull();
        } else {
            $isRaw = false;
            $canPrependFull = true;

            if (strpos($link, '#') !== false) {
                list($link, $hash) = explode('#', $link);
            }
        }

        if (XenForo_Link::$_useFriendlyUrls || $isRaw) {
            $outputLink = ($queryString !== '' ? "$link?$queryString" : $link);
        } else {
            if ($queryString !== '' && $link !== '') {
                $append = "?$link&$queryString";
            } else {
                // 1 or neither of these has content
                $append = $link . $queryString;
                if ($append !== '') {
                    $append = "?$append";
                }
            }
            if ($skipPrepend) {
                $outputLink = $append;
            } else {
                $outputLink = 'index.php' . $append;
            }
        }

        if ($fullLink && $canPrependFull) {
            $outputLink = $fullLinkPrefix . $outputLink;
        }

        // deal with a hash in the $type {xen:link prefix#hash..}
        if (($hashPos = strpos($type, '#')) !== false) {
            $hash = substr($type, $hashPos + 1);
        }

        if ($outputLink === '') {
            $outputLink = '.';
        }

        return $outputLink . (empty($hash) ? '' : '#' . $hash);
    }

    public static function convertUriToAbsoluteUri($uri, $includeHost = false, array $paths = null)
    {
        if (!$paths) {
            $paths = XenForo_Application::get('requestPaths');
        }

        $boardUrl = rtrim(XenForo_Application::getOptions()->get('boardUrl'), '/') . '/';
        $boardUrlParsed = parse_url($boardUrl);

        $paths['protocol'] = $boardUrlParsed['scheme'];
        $paths['host'] = $boardUrlParsed['host'] . (isset($boardUrlParsed['port']) ? (':' . $boardUrlParsed) : '');
        $paths['fullBasePath'] = $boardUrl;
        $paths['basePath'] = $boardUrlParsed['path'];

        return parent::convertUriToAbsoluteUri($uri, true, $paths);
    }

    public static function convertApiUriToAbsoluteUri($uri, $includeHost = false, array $paths = null)
    {
        return parent::convertUriToAbsoluteUri($uri, true, $paths);
    }

    protected static function _checkForFullLink($type, &$fullLink, &$fullLinkPrefix)
    {
        $type = parent::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

        if (!empty($fullLinkPrefix)) {
            // fix issue with HTTPS requests
            $paths = XenForo_Application::get('requestPaths');

            if ($paths['protocol'] === 'https' AND parse_url($fullLinkPrefix, PHP_URL_SCHEME) === 'http') {
                $fullLinkPrefix = str_replace('http://', 'https://', $fullLinkPrefix);
            }
        }

        return $type;
    }

}

eval('class XenForo_Link extends bdApi_Link {}');
