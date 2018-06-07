<?php

class bdApiConsumer_Helper_Api
{
    const SCOPE = 'read post';

    public static function getRequestUrl(array $provider, $redirectUri, array $extraParams = array())
    {
        $url = call_user_func_array('sprintf', array(
            '%s/index.php?oauth/authorize/&client_id=%s&redirect_uri=%s&response_type=code&scope=%s',
            rtrim($provider['root'], '/'),
            rawurlencode($provider['client_id']),
            rawurlencode($redirectUri),
            rawurlencode(self::SCOPE),
        ));

        if (XenForo_Application::getConfig()->get(bdApiConsumer_Option::CONFIG_TRACK_AUTHORIZE_URL_STATE)
            && !isset($extraParams['state'])
        ) {
            $extraParams['state'] = base64_encode(json_encode(array(
                'time' => XenForo_Application::$time,
                'ip' => XenForo_Helper_Ip::convertIpBinaryToString(XenForo_Application::getSession()->get('ip')),
            )));
        }

        foreach ($extraParams as $key => $value) {
            $url .= sprintf('&%s=%s', $key, rawurlencode($value));
        }

        return $url;
    }

    public static function getLoginLink(array $provider, $accessToken, $redirectUri)
    {
        return call_user_func_array('sprintf', array(
            '%s/index.php?tools/login&oauth_token=%s&redirect_uri=%s',
            rtrim($provider['root'], '/'),
            rawurlencode($accessToken),
            rawurlencode($redirectUri),
        ));
    }

    public static function getLogoutLink(array $provider, $accessToken, $redirectUri)
    {
        return call_user_func_array('sprintf', array(
            '%s/index.php?tools/logout&oauth_token=%s&redirect_uri=%s',
            rtrim($provider['root'], '/'),
            rawurlencode($accessToken),
            rawurlencode($redirectUri),
        ));
    }

    public static function getPublicLink(array $provider, $route)
    {
        $json = self::_post($provider, 'tools/link/', self::generateOneTimeToken($provider), 'link', array(
            'type' => 'public',
            'route' => $route,
        ));

        if (!empty($json['link'])) {
            return $json['link'];
        }

        return false;
    }

    public static function getAccessTokenFromCode(array $provider, $code, $redirectUri)
    {
        return self::_post($provider, 'oauth/token/', '', 'access_token', array(
            'grant_type' => 'authorization_code',
            'client_id' => $provider['client_id'],
            'client_secret' => $provider['client_secret'],
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ));
    }

    public static function getAccessTokenFromRefreshToken(array $provider, $refreshToken)
    {
        return self::_post($provider, 'oauth/token/', '', 'access_token', array(
            'grant_type' => 'refresh_token',
            'client_id' => $provider['client_id'],
            'client_secret' => $provider['client_secret'],
            'refresh_token' => $refreshToken,
        ));
    }

    public static function getAccessTokenFromUsernamePassword(array $provider, $username, $password)
    {
        return self::_post($provider, 'oauth/token/', '', 'access_token', array(
            'grant_type' => 'password',
            'client_id' => $provider['client_id'],
            'client_secret' => $provider['client_secret'],
            'username' => $username,
            'password' => $password,
        ));
    }

    public static function generateOneTimeToken(array $provider, $userId = 0, $accessToken = '', $ttl = 10)
    {
        $timestamp = time() + $ttl;
        $once = md5($userId . $timestamp . $accessToken . $provider['client_secret']);

        return sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $provider['client_id']);
    }

    /**
     * @param array $provider
     * @param $accessToken
     * @param bool|true $autoSubscribe
     * @return array|false
     */
    public static function getVisitor(array $provider, $accessToken, $autoSubscribe = true)
    {
        $json = self::_get($provider, 'users/me/', $accessToken, 'user');

        if (!empty($json['user'])) {
            $json['_headerLinkHub'] = self::_getHeaderLinkHub($json['_headers']);

            if ($autoSubscribe AND empty($parts['subscription_callback']) AND !empty($json['_headerLinkHub'])) {
                self::postSubscription($provider, $accessToken, $json['_headerLinkHub']);
            }

            return $json['user'];
        }

        return false;
    }

    public static function getNotifications(array $provider, $accessToken)
    {
        $json = self::_get($provider, 'notifications/', $accessToken, 'notifications');

        if (isset($json['notifications'])) {
            $json['_headerLinkHub'] = self::_getHeaderLinkHub($json['_headers']);

            return $json;
        }

        return false;
    }

    public static function postLoginSocial(array $provider)
    {
        return self::_post($provider, 'tools/login-social/', self::generateOneTimeToken($provider), 'social');
    }

    public static function postPasswordResetRequest(array $provider, $accessToken)
    {
        return self::_post($provider, 'tools/password-reset-request/', $accessToken, 'status');
    }

    public static function postSubscription(array $provider, $accessToken, $url)
    {
        $json = self::_post($provider, $url, $accessToken, false, array(
            'hub.callback' => XenForo_Link::buildPublicLink('canonical:misc/api-consumer/callback'),
            'hub.mode' => 'subscribe',
        ));

        return (!empty($json['_responseStatus']) AND $json['_responseStatus'] == 202);
    }

    public static function postNotificationsRead(array $provider, $accessToken)
    {
        $json = self::_post($provider, 'notifications/read/', $accessToken);

        return (!empty($json['_responseStatus']) AND $json['_responseStatus'] == 200);
    }

    public static function verifyJsSdkSignature(array $provider, array $data, $prefix = '_api_data_')
    {
        $str = '';
        $prefixLength = utf8_strlen($prefix);

        $keys = array_keys($data);
        asort($keys);
        foreach ($keys as $key) {
            if (utf8_substr($key, 0, $prefixLength) !== $prefix) {
                // ignore keys that do not match our prefix
                continue;
            }

            $keySubstr = substr($key, $prefixLength);
            if ($keySubstr == 'signature') {
                // do not put the signature into calculation
                continue;
            }

            $str .= sprintf('%s=%s&', $keySubstr, $data[$key]);
        }
        $str .= $provider['client_secret'];

        $signature = md5($str);

        return isset($data[$prefix . 'signature']) AND ($signature === $data[$prefix . 'signature']);
    }

    protected static function _get(array $provider, $path, $accessToken = false, $expectedKey = false, array $params = array())
    {
        return self::_request('GET', $provider, $path, $accessToken, $expectedKey, $params);
    }

    protected static function _post(array $provider, $path, $accessToken = false, $expectedKey = false, array $params = array())
    {
        return self::_request('POST', $provider, $path, $accessToken, $expectedKey, $params);
    }

    protected static function _request($method, array $provider, $path, $accessToken = false, $expectedKey = false, array $params = array())
    {
        try {
            if (Zend_Uri::check($path)) {
                $uri = $path;
            } else {
                $uri = call_user_func_array('sprintf', array(
                    '%s/index.php?%s',
                    rtrim($provider['root'], '/'),
                    $path,
                ));
            }
            $client = XenForo_Helper_Http::getClient($uri);

            if ($accessToken !== false AND !isset($params['oauth_token'])) {
                $params['oauth_token'] = $accessToken;
            }

            if ($method === 'GET') {
                $client->setParameterGet($params);
            } else {
                $client->setParameterPost($params);
            }

            $response = $client->request($method);

            $body = $response->getBody();
            $json = @json_decode($body, true);

            if (!is_array($json)) {
                $json = array('_body' => $body);
            }

            if ($expectedKey !== false) {
                if (!isset($json[$expectedKey])) {
                    if (XenForo_Application::debugMode()) {
                        XenForo_Error::logError(sprintf(
                            'Key "%s" not found in %s `%s`: %s',
                            $expectedKey,
                            $method,
                            $path,
                            $body
                        ));
                    }
                    return false;
                }
            }

            $json['_headers'] = $response->getHeaders();
            $json['_responseStatus'] = $response->getStatus();

            return $json;
        } catch (Zend_Http_Client_Exception $e) {
            if (XenForo_Application::debugMode()) {
                XenForo_Error::logException($e, false);
            }
            return false;
        }
    }

    protected static function _getHeaderLinkHub(array $headers)
    {
        if (empty($headers['Link'])) {
            return null;
        }

        foreach ($headers['Link'] as $headerLink) {
            if (preg_match('/<(?<url>[^>]+)>; rel=hub/', $headerLink, $matches)) {
                return $matches['url'];
            }
        }

        return null;
    }
}
