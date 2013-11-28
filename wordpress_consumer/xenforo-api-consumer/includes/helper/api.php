<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_api_getAuthorizeUrl($root, $clientId, $clientSecret, $redirectUri)
{
	return call_user_func_array('sprintf', array(
		'%s/oauth/authorize/?client_id=%s&redirect_uri=%s&response_type=code&scope=read',
		$root,
		rawurlencode($clientId),
		rawurlencode($redirectUri)
	));
}

function xfac_api_getAccessTokenFromCode($root, $clientId, $clientSecret, $code, $redirectUri)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, sprintf('%s/oauth/token/', $root));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'grant_type' => 'authorization_code',
		'client_id' => $clientId,
		'client_secret' => $clientSecret,
		'code' => $code,
		'redirect_uri' => $redirectUri,
		'scope' => 'read',
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['access_token']))
	{
		return $parts;
	}
	else
	{
		return false;
	}
}

function xfac_api_getUsersMe($root, $clientId, $clientSecret, $accessToken)
{
	$body = file_get_contents(sprintf('%s/users/me/?oauth_token=%s', $root, rawurlencode($accessToken)));

	$parts = @json_decode($body, true);

	if (!empty($parts['user']))
	{
		return $parts;
	}
	else
	{
		return false;
	}
}
