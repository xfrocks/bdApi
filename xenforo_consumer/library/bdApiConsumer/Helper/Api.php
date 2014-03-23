<?php
class bdApiConsumer_Helper_Api
{
	public static function getRequestUrl(array $provider, $redirectUri)
	{
		return call_user_func_array('sprintf', array(
			'%s/index.php?oauth/authorize/&client_id=%s&redirect_uri=%s&response_type=code&scope=read',
			rtrim($provider['root'], '/'),
			rawurlencode($provider['client_id']),
			rawurlencode($redirectUri)
		));
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
		try
		{
			$uri = call_user_func_array('sprintf', array(
				'%s/index.php?tools/link',
				rtrim($provider['root'], '/')
			));
			$client = XenForo_Helper_Http::getClient($uri);
			$client->setParameterPost(array(
				'oauth_token' => self::generateOneTimeToken($provider),
				'type' => 'public',
				'route' => $route,
			));
			$response = $client->request('POST');

			$body = $response->getBody();
			$parts = @json_decode($body, true);

			if (!empty($parts['link']))
			{
				return $parts['link'];
			}
			else
			{
				XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to parse `link` from `%s`', $body)), false);
				return false;
			}
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

	public static function getAccessTokenFromCode(array $provider, $code, $redirectUri)
	{
		try
		{
			$uri = call_user_func_array('sprintf', array(
				'%s/index.php?oauth/token/',
				rtrim($provider['root'], '/'),
			));
			$client = XenForo_Helper_Http::getClient($uri);
			$client->setParameterPost(array(
				'grant_type' => 'authorization_code',
				'client_id' => $provider['client_id'],
				'client_secret' => $provider['client_secret'],
				'code' => $code,
				'redirect_uri' => $redirectUri,
				'scope' => 'read',
			));

			$response = $client->request('POST');

			$body = $response->getBody();
			$parts = @json_decode($body, true);

			if (!empty($parts['access_token']))
			{
				return $parts;
			}
			else
			{
				XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to parse `access_token` from `%s`', $body)), false);
				return false;
			}
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

	public static function getAccessTokenFromRefreshToken(array $provider, $refreshToken, $scope)
	{
		try
		{
			$uri = call_user_func_array('sprintf', array(
				'%s/index.php?oauth/token/',
				rtrim($provider['root'], '/')
			));
			$client = XenForo_Helper_Http::getClient($uri);
			$client->setParameterPost(array(
				'grant_type' => 'refresh_token',
				'client_id' => $provider['client_id'],
				'client_secret' => $provider['client_secret'],
				'refresh_token' => $refreshToken,
				'scope' => $scope,
			));

			$response = $client->request('POST');

			$body = $response->getBody();
			$parts = @json_decode($body, true);

			if (!empty($parts['access_token']))
			{
				return $parts;
			}
			else
			{
				XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to parse `access_token` from `%s`', $body)), false);
				return false;
			}
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

	public static function getAccessTokenFromUsernamePassword(array $provider, $username, $password)
	{
		try
		{
			$uri = call_user_func_array('sprintf', array(
				'%s/index.php?oauth/token/',
				rtrim($provider['root'], '/')
			));
			$client = XenForo_Helper_Http::getClient($uri);
			$client->setParameterPost(array(
				'grant_type' => 'password',
				'client_id' => $provider['client_id'],
				'client_secret' => $provider['client_secret'],
				'username' => $username,
				'password' => $password,
			));
			$response = $client->request('POST');

			$body = $response->getBody();
			$parts = @json_decode($body, true);

			if (!empty($parts['access_token']))
			{
				return $parts;
			}
			else
			{
				XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to parse `access_token` from `%s`', $body)), false);
				return false;
			}
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
	}

	public static function generateOneTimeToken(array $provider, $userId = 0, $accessToken = '', $ttl = 10)
	{
		$timestamp = time() + $ttl;
		$once = md5($userId . $timestamp . $accessToken . $provider['client_secret']);

		return sprintf('%d,%d,%s,%s', $userId, $timestamp, $once, $provider['client_id']);
	}

	public static function getVisitor(array $provider, $accessToken)
	{
		try
		{
			$uri = call_user_func_array('sprintf', array(
				'%s/index.php?users/me/',
				rtrim($provider['root'], '/'),
			));
			$client = XenForo_Helper_Http::getClient($uri);
			$client->setParameterGet(array('oauth_token' => $accessToken));

			$response = $client->request('GET');

			$body = $response->getBody();
			$parts = @json_decode($body, true);

			if (!empty($parts) AND !empty($parts['user']))
			{
				return $parts['user'];
			}
			else
			{
				XenForo_Error::logException(new XenForo_Exception(sprintf('Unable to get user info from `%s`', $body)), false);
				return false;
			}
		}
		catch (Zend_Http_Client_Exception $e)
		{
			XenForo_Error::logException($e, false);
			return false;
		}
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

}
