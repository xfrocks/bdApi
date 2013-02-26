<?php
class bdApiConsumer_Helper_Api
{
	public static function getRequestUrl(array $provider, $redirectUri)
	{
		return sprintf('%s/oauth/authorize/?client_id=%s&redirect_uri=%s&response_type=code&scope=read', $provider['root'], $provider['client_id'], $redirectUri);
	}

	public static function getAccessTokenFromCode(array $provider, $code, $redirectUri)
	{
		try
		{
			$uri = sprintf('%s/oauth/token/', $provider['root']);
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
				
			if (!empty($parts) AND !empty($parts['access_token']))
			{
				return $parts['access_token'];
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
	
	public static function getVisitor(array $provider, $accessToken)
	{
		try
		{
			$uri = sprintf('%s/users/me/', $provider['root']);
			$client = XenForo_Helper_Http::getClient($uri);
			$client->setParameterGet(array(
				'oauth_token' => $accessToken,
			));

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
}