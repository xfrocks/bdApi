<?php

$xenforoLinkPath = XenForo_Application::getInstance()->getRootDir() . '/library/XenForo/Link.php';
$xenforoLinkContents = file_get_contents($xenforoLinkPath);
$xenforoLinkContents = substr($xenforoLinkContents, 6); // remove <?php\n
$xenforoLinkContents = str_replace('class XenForo_Link', 'class _XenForo_Link', $xenforoLinkContents);
eval($xenforoLinkContents);

class XenForo_Link extends _XenForo_Link
{
	public static function buildPublicLink($type, $data = null, array $extraParams = array(), $skipPrepend = false)
	{
		return bdApi_Link::buildPublicLink($type, $data, $extraParams, $skipPrepend);
	}

	public static function convertUriToAbsoluteUri($uri, $includeHost = false, array $paths = null)
	{
		return bdApi_Link::convertUriToAbsoluteUri($uri, $includeHost, $paths);
	}
}

class bdApi_Link extends XenForo_Link
{
	const API_LINK_GROUP = 'api';
	const PUBLIC_LINK_GROUP = 'apiPublic';

	/**
	 * Builds public link. Most of the code is copied from
	 * XenForo_Link::buildPublicLink() but the group has been changed
	 * to self::PUBLIC_LINK_GROUP.
	 *
	 * @param string $type
	 * @param miaxed $data
	 * @param array $extraParams
	 * @param bool $skipPrepend
	 */
	public static function buildPublicLink($type, $data = null, array $extraParams = array(), $skipPrepend = false)
	{
		// the type MUST BE canonical:$type
		// NOTE: this is the opposite with api links
		if (strpos($type, 'full:') === 0)
		{
			// replace full: with canonical:
			$type = str_replace('full:', 'canonical:', $type);
		}
		elseif (strpos($type, 'canonical:') === false)
		{
			// enforce canonical:
			$type = 'canonical:' . $type;
		}

		$type = self::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

		$link = self::_buildLink(self::PUBLIC_LINK_GROUP, $type, $data, $extraParams);
		$queryString = self::buildQueryString($extraParams);

		if ($link instanceof XenForo_Link)
		{
			$isRaw = true;
			$canPrependFull = $link->canPrependFull();
		}
		else
		{
			$isRaw = false;
			$canPrependFull = true;

			if (strpos($link, '#') !== false)
			{
				list($link, $hash) = explode('#', $link);
			}
		}

		if (self::$_useFriendlyUrls || $isRaw)
		{
			$outputLink = ($queryString !== '' ? "$link?$queryString" : $link);
		}
		else
		{
			if ($queryString !== '' && $link !== '')
			{
				$append = "?$link&$queryString";
			}
			else
			{
				// 1 or neither of these has content
				$append = $link . $queryString;
				if ($append !== '')
				{
					$append = "?$append";
				}
			}
			if ($skipPrepend)
			{
				$outputLink = $append;
			}
			else
			{
				$outputLink = 'index.php' . $append;
			}
		}

		if ($fullLink && $canPrependFull)
		{
			$outputLink = $fullLinkPrefix . $outputLink;
		}

		// deal with a hash in the $type {xen:link prefix#hash..}
		if (($hashPos = strpos($type, '#')) !== false)
		{
			$hash = substr($type, $hashPos + 1);
		}

		if ($outputLink === '')
		{
			$outputLink = '.';
		}

		return $outputLink . (empty($hash) ? '' : '#' . $hash);
	}

	/**
	 * Builds link to api methods. Basically a simplified version of
	 * XenForo_Link::buildPublicLink
	 *
	 * @param string $type
	 * @param mixed $data
	 * @param array $extraParams
	 * @param bool $skipPrepend
	 */
	public static function buildApiLink($type, $data = null, array $extraParams = array(), $skipPrepend = false)
	{
		// the type MUST BE full:type
		// NOTE: this is the opposite with public links
		if (strpos($type, 'canonical:') === 0)
		{
			// replace canonical: with full:
			$type = str_replace('canonical:', 'full:', $type);
		}
		elseif (strpos($type, 'full:') === false)
		{
			// enforce full:
			$type = 'full:' . $type;
		}

		// auto appends oauth_token param from the session
		if (!isset($extraParams[OAUTH2_TOKEN_PARAM_NAME]))
		{
			$session = XenForo_Application::get('session');
			$oauthToken = $session->getOAuthTokenText();
			if (!empty($oauthToken))
			{
				$extraParams[OAUTH2_TOKEN_PARAM_NAME] = $oauthToken;
			}
		}

		$type = self::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

		$link = parent::_buildLink(self::API_LINK_GROUP, $type, $data, $extraParams);
		$queryString = self::buildQueryString($extraParams);

		if ($link instanceof XenForo_Link)
		{
			$isRaw = true;
			$canPrependFull = $link->canPrependFull();
		}
		else
		{
			$isRaw = false;
			$canPrependFull = true;

			if (strpos($link, '#') !== false)
			{
				list($link, $hash) = explode('#', $link);
			}
		}

		if (self::$_useFriendlyUrls || $isRaw)
		{
			$outputLink = ($queryString !== '' ? "$link?$queryString" : $link);
		}
		else
		{
			if ($queryString !== '' && $link !== '')
			{
				$append = "?$link&$queryString";
			}
			else
			{
				// 1 or neither of these has content
				$append = $link . $queryString;
				if ($append !== '')
				{
					$append = "?$append";
				}
			}
			if ($skipPrepend)
			{
				$outputLink = $append;
			}
			else
			{
				$outputLink = 'index.php' . $append;
			}
		}

		if ($fullLink && $canPrependFull)
		{
			$outputLink = $fullLinkPrefix . $outputLink;
		}

		return $outputLink;
	}

	public static function convertUriToAbsoluteUri($uri, $includeHost = false, array $paths = null)
	{
		if (Zend_Uri::check($uri))
		{
			return $uri;
		}

		$boardUrl = XenForo_Application::getOptions()->get('boardUrl');
		$boardUrlParsed = parse_url($boardUrl);

		if ($uri == '.')
		{
			// current directory
			$uri = '';
		}

		if (substr($uri, 0, 2) == '//')
		{
			return $boardUrlParsed['scheme'] . ':' . $uri;
		}
		elseif (substr($uri, 0, 1) == '/')
		{
			return $boardUrlParsed['scheme'] . '://' . $boardUrlParsed['host'] . (isset($boardUrlParsed['port']) ? (':' . $boardUrlParsed) : '') . $uri;
		}
		elseif (preg_match('#^[a-z0-9-]+://#i', $uri))
		{
			return $uri;
		}
		else
		{
			return $boardUrl . '/' . $uri;
		}
	}

	public static function convertApiUriToAbsoluteUri($uri, $includeHost = false, array $paths = null)
	{
		return _XenForo_Link::convertUriToAbsoluteUri($uri, $includeHost, $paths);
	}

	protected static function _checkForFullLink($type, &$fullLink, &$fullLinkPrefix)
	{
		$type = XenForo_Link::_checkForFullLink($type, $fullLink, $fullLinkPrefix);

		if (!empty($fullLinkPrefix))
		{
			// fix issue with HTTPS requests
			$paths = XenForo_Application::get('requestPaths');

			if ($paths['protocol'] === 'https' AND parse_url($fullLinkPrefix, PHP_URL_SCHEME) === 'http')
			{
				$fullLinkPrefix = str_replace('http://', 'https://', $fullLinkPrefix);
			}
		}

		return $type;
	}

}
