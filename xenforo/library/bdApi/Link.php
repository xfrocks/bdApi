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
    public function __construct($linkString, $canPrependFull = true)
    {
        if ($canPrependFull) {
            // we have to verify this because caller may not know that all relative urls are forced to absolute by us
            $canPrependFull = !Zend_Uri::check($linkString);
        }

        parent::__construct($linkString, $canPrependFull);
    }

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

        $session = bdApi_Data_Helper_Core::safeGetSession();
        if (!empty($session)) {
            // auto appends oauth_token param from the session
            if (!isset($extraParams['oauth_token'])) {
                $oauthToken = $session->getOAuthTokenText();
                if (!empty($oauthToken)
                    && !empty($_REQUEST['oauth_token'])
                    && $_REQUEST['oauth_token'] === $oauthToken
                ) {
                    // only append token to built link if the current request has token in query too
                    // this will prevent token in links if it's requested with OTT, token in Auth header
                    // or token in body (PUT/POST requests)
                    $extraParams['oauth_token'] = $oauthToken;
                }
            }

            // auto appends locale param from session
            if (!isset($extraParams['locale'])) {
                $locale = $session->get('requestLocale');
                if (!empty($locale)) {
                    $extraParams['locale'] = $locale;
                }
            }
        }

        $type = XenForo_Link::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

        $link = XenForo_Link::_buildLink('api', $type, $data, $extraParams);
        $queryString = XenForo_Link::buildQueryString($extraParams);

        if ($link instanceof XenForo_Link) {
            $canPrependFull = $link->canPrependFull();
        } else {
            $canPrependFull = true;

            if (strpos($link, '#') !== false) {
                list($link, $hash) = explode('#', $link);
            }
        }

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
        $paths['host'] = $boardUrlParsed['host'] .
            (isset($boardUrlParsed['port']) ?
                (':' . $boardUrlParsed['port']) : '');
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

    protected static function _buildLink($group, $type, $data, array &$extraParams, &$prefix = null)
    {
        $built = parent::_buildLink($group, $type, $data, $extraParams, $prefix);

        if ($group === 'public') {
            $session = bdApi_Data_Helper_Core::safeGetSession();
            if (!empty($session)) {
                // auto appends locale param from session
                if (!isset($extraParams['locale'])) {
                    $locale = $session->get('requestLocale');
                    if (!empty($locale)) {
                        $timestamp = time() + 86400;
                        $extraParams['_apiLanguageId'] = sprintf(
                            '%s %s',
                            $timestamp,
                            bdApi_Crypt::encryptTypeOne($session->get('languageId'), $timestamp)
                        );
                    }
                }
            }
        }

        return $built;
    }
}

eval('class XenForo_Link extends bdApi_Link {}');
