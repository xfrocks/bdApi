<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_api_getAuthorizeUrl($root, $clientId, $clientSecret, $redirectUri)
{
	return call_user_func_array('sprintf', array(
		'%s/oauth/authorize/?client_id=%s&redirect_uri=%s&response_type=code&scope=%s',
		$root,
		rawurlencode($clientId),
		rawurlencode($redirectUri),
		rawurlencode(XFAC_API_SCOPE),
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
		'scope' => XFAC_API_SCOPE,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['access_token']))
	{
		if (!empty($parts['expires_in']))
		{
			$parts['expire_date'] = time() + $parts['expires_in'];
		}

		return $parts;
	}
	else
	{
		return false;
	}
}

function xfac_api_getAccessTokenFromRefreshToken($root, $clientId, $clientSecret, $refreshToken, $scope)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, sprintf('%s/oauth/token/', $root));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'grant_type' => 'refresh_token',
		'client_id' => $clientId,
		'client_secret' => $clientSecret,
		'refresh_token' => $refreshToken,
		'scope' => $scope,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['access_token']))
	{
		if (!empty($parts['expires_in']))
		{
			$parts['expire_date'] = time() + $parts['expires_in'];
		}

		return $parts;
	}
	else
	{
		return false;
	}
}

function xfac_api_getForums($root, $clientId, $clientSecret, $accessToken = '')
{
	$body = file_get_contents(sprintf('%s/forums/?oauth_token=%s', $root, rawurlencode($accessToken)));

	$parts = @json_decode($body, true);

	if (!empty($parts['forums']))
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

function xfac_api_getThreadsInForum($root, $clientId, $clientSecret, $forumId, $page = 1, $accessToken = '')
{
	$body = file_get_contents(sprintf('%s/threads/?forum_id=%d&page=%d&order=thread_create_date_reverse&oauth_token=%s', $root, $forumId, $page, rawurlencode($accessToken)));

	$parts = @json_decode($body, true);

	if (!empty($parts['threads']))
	{
		return $parts;
	}
	else
	{
		return false;
	}
}

function xfac_api_getPostsInThread($root, $clientId, $clientSecret, $threadId, $page = 1, $accessToken = '')
{
	$body = file_get_contents(sprintf('%s/posts/?thread_id=%d&page=%d&order=natural_reverse&oauth_token=%s', $root, $threadId, $page, rawurlencode($accessToken)));

	$parts = @json_decode($body, true);

	if (!empty($parts['posts']))
	{
		return $parts;
	}
	else
	{
		return false;
	}
}

function xfac_api_postThread($root, $clientId, $clientSecret, $accessToken, $forumId, $threadTitle, $postBody)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, sprintf('%s/threads/', $root));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'oauth_token' => $accessToken,
		'forum_id' => $forumId,
		'thread_title' => $threadTitle,
		'post_body' => $postBody,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['thread']))
	{
		return $parts;
	}
	else
	{
		return false;
	}
}

function xfac_api_postPost($root, $clientId, $clientSecret, $accessToken, $threadId, $postBody)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, sprintf('%s/posts/', $root));
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'oauth_token' => $accessToken,
		'thread_id' => $threadId,
		'post_body' => $postBody,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);

	$parts = @json_decode($body, true);

	if (!empty($parts['post']))
	{
		return $parts;
	}
	else
	{
		return false;
	}
}
