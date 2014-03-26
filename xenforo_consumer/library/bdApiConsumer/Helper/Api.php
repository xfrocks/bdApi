<?php
class bdApiConsumer_Helper_Api
{
	public static function getRequestUrl(array $provider, $redirectUri, array $extraParams = array())
	{
		$url = call_user_func_array('sprintf', array(
			'%s/index.php?oauth/authorize/&client_id=%s&redirect_uri=%s&response_type=code&scope=read',
			rtrim($provider['root'], '/'),
			rawurlencode($provider['client_id']),
			rawurlencode($redirectUri)
		));

		foreach ($extraParams as $key => $value)
		{
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

		if (!empty($json['link']))
		{
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
			'scope' => 'read',
		));
	}

	public static function getAccessTokenFromRefreshToken(array $provider, $refreshToken, $scope)
	{
		return self::_post($provider, 'oauth/token/', '', 'access_token', array(
			'grant_type' => 'refresh_token',
			'client_id' => $provider['client_id'],
			'client_secret' => $provider['client_secret'],
			'refresh_token' => $refreshToken,
			'scope' => $scope,
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

	public static function getVisitor(array $provider, $accessToken)
	{
		$json = self::_get($provider, 'users/me/', $accessToken, 'user');

		if (!empty($json['user']))
		{
			return $json['user'];
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

	public static function verifyJsSdkSignature(array $provider, array $data, $prefix = '_api_data_')
	{
		$str = '';
		$prefixLength = utf8_strlen($prefix);

		$keys = array_keys($data);
		asort($keys);
		foreach ($keys as $key)
		{
			if (utf8_substr($key, 0, $prefixLength) !== $prefix)
			{
				// ignore keys that do not match our prefix
				continue;
			}

			$keySubstr = substr($key, $prefixLength);
			if ($keySubstr == 'signature')
			{
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
		try
		{
			$uri = call_user_func_array('sprintf', array(
				'%s/index.php?%s',
				rtrim($provider['root'], '/'),
				$path,
			));
			$client = XenForo_Helper_Http::getClient($uri);

			if ($accessToken !== false AND !isset($params['oauth_token']))
			{
				$params['oauth_token'] = $accessToken;
			}
			$client->setParameterGet($params);

			$response = $client->request('GET');

			$body = $response->getBody();
			$json = @json_decode($body, true);

			if (!is_array($json))
			{
				return false;
			}

			if ($expectedKey !== false)
			{
				if (!isset($json[$expectedKey]))
				{
					XenForo_Error::logException(sprintf('Key "%s" not found in GET `%s`: %s', $expectedKey, $path, $body), false);
					return false;
				}
			}

			return $json;
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

	protected static function _post(array $provider, $path, $accessToken = false, $expectedKey = false, array $params = array())
	{
		try
		{
			$uri = call_user_func_array('sprintf', array(
				'%s/index.php?%s',
				rtrim($provider['root'], '/'),
				$path,
			));
			$client = XenForo_Helper_Http::getClient($uri);

			if ($accessToken !== false AND !isset($params['oauth_token']))
			{
				$params['oauth_token'] = $accessToken;
			}
			$client->setParameterPost($params);

			$response = $client->request('POST');

			$body = $response->getBody();
			$json = @json_decode($body, true);

			if (!is_array($json))
			{
				return false;
			}

			if ($expectedKey !== false)
			{
				if (!isset($json[$expectedKey]))
				{
					XenForo_Error::logException(sprintf('Key "%s" not found in GET `%s`: %s', $expectedKey, $path, $body), false);
					return false;
				}
			}

			return $json;
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

}
