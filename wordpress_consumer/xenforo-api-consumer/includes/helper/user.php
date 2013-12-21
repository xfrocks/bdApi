<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_user_getUserDataByApiData($root, $xfUserId)
{
	global $wpdb;

	// TODO: support multiple providers?
	$provider = '';

	$userdata = $wpdb->get_row($wpdb->prepare("
		SELECT users.*
		FROM {$wpdb->prefix}xfac_auth AS auth
		INNER JOIN $wpdb->users AS users
		ON (users.ID = auth.user_id)
		WHERE auth.provider = %s AND auth.identifier = %s
	", $provider, $xfUserId));

	if (empty($userdata))
	{
		return false;
	}

	return $userdata;
}

function xfac_user_getUserByApiData($root, $xfUserId)
{
	$userdata = xfac_user_getUserDataByApiData($root, $xfUserId);

	if (empty($userdata))
	{
		return false;
	}

	$user = new WP_User;
	$user->init($userdata);

	return $user;
}

function xfac_user_updateAuth($wfUserId, $root, $xfUserId, array $xfUser, array $token)
{
	global $wpdb;

	$provider = '';

	$wpdb->query($wpdb->prepare("
		REPLACE INTO {$wpdb->prefix}xfac_auth
		(user_id, provider, identifier, profile, token)
		VALUES (%d, %s, %s, %s, %s)
	", $wfUserId, $provider, $xfUserId, serialize($xfUser), serialize($token)));
}

function xfac_user_getAccessToken($wfUserId)
{
	global $wpdb;

	$auth = $wpdb->get_row($wpdb->prepare("
		SELECT *
		FROM {$wpdb->prefix}xfac_auth
		WHERE user_id = %d
	", $wfUserId));

	if (empty($auth))
	{
		return false;
	}

	$token = unserialize($auth->token);
	if (!empty($token['expire_date']) AND $token['expire_date'] > time())
	{
		return $token['access_token'];
	}

	if (empty($token['refresh_token']))
	{
		return null;
	}

	$root = get_option('xfac_root');
	$clientId = get_option('xfac_client_id');
	$clientSecret = get_option('xfac_client_secret');
	if (empty($root) OR empty($clientId) OR empty($clientSecret))
	{
		return null;
	}

	$newToken = xfac_api_getAccessTokenFromRefreshToken($root, $clientId, $clientSecret, $token['refresh_token'], $token['scope']);

	if (empty($newToken))
	{
		return null;
	}

	xfac_user_updateAuth($wfUserId, $root, $auth->identifier, unserialize($auth->profile), $newToken);

	return $newToken['access_token'];
}
