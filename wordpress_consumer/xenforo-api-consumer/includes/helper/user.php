<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_user_getRecordsByUserId($wpUserId)
{
	global $wpdb;

	$cache = wp_cache_get($wpUserId, XFAC_CACHE_RECORDS_BY_USER_ID);
	if (is_array($cache))
	{
		return $cache;
	}

	$tblAuth = xfac_getTableAuth();

	$records = $wpdb->get_results($wpdb->prepare("
		SELECT *
		FROM {$tblAuth}
		WHERE user_id = %d
	", $wpUserId));

	foreach ($records as &$record)
	{
		$record->profile = unserialize($record->profile);
		$record->token = unserialize($record->token);
	}

	wp_cache_set($wpUserId, $records, XFAC_CACHE_RECORDS_BY_USER_ID, XFAC_CACHE_RECORDS_BY_USER_ID_TTL);

	return $records;
}

function xfac_user_getUserDataByApiData($root, $xfUserId)
{
	global $wpdb;

	$tblAuth = xfac_getTableAuth();
	$provider = '';

	$userdata = $wpdb->get_row($wpdb->prepare("
		SELECT users.*
		FROM {$tblAuth} AS auth
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

function xfac_user_updateRecord($wpUserId, $root, $xfUserId, array $xfUser, array $token)
{
	global $wpdb;

	$tblAuth = xfac_getTableAuth();
	$provider = '';

	wp_cache_delete($wpUserId, XFAC_CACHE_RECORDS_BY_USER_ID);

	return $wpdb->query($wpdb->prepare("
		REPLACE INTO {$tblAuth}
		(user_id, provider, identifier, profile, token)
		VALUES (%d, %s, %s, %s, %s)
	", $wpUserId, $provider, $xfUserId, serialize($xfUser), serialize($token)));
}

function xfac_user_deleteRecord($record)
{
	global $wpdb;

	$tblAuth = xfac_getTableAuth();

	wp_cache_delete($record->user_id, XFAC_CACHE_RECORDS_BY_USER_ID);

	return $wpdb->delete($tblAuth, array('id' => $record->id));
}

function xfac_user_getSystemAccessToken($generateOneTimeToken = false, &$isOneTime = false)
{
	$accessToken = null;

	if (intval(get_option('xfac_xf_guest_account')) > 0)
	{
		// use pre-configured system account
		$accessToken = xfac_user_getAccessToken(0);
	}
	elseif ($generateOneTimeToken)
	{
		// use one time token for guest
		$accessToken = xfac_api_generateOneTimeToken($config);
		$isOneTime = true;
	}

	return $accessToken;
}

function xfac_user_getAccessToken($wpUserId)
{
	$records = xfac_user_getRecordsByUserId($wpUserId);
	if (empty($records))
	{
		return null;
	}

	$record = reset($records);

	return xfac_user_getAccessTokenForRecord($record);
}

function xfac_user_getAccessTokenForRecord($record)
{
	$token = $record->token;

	if (!empty($token['expire_date']) AND $token['expire_date'] > time())
	{
		return $token['access_token'];
	}

	if (empty($token['refresh_token']))
	{
		return null;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return null;
	}

	$newToken = xfac_api_getAccessTokenFromRefreshToken($config, $token['refresh_token'], $token['scope']);

	if (empty($newToken))
	{
		return null;
	}

	xfac_user_updateRecord($record->user_id, $config['root'], $record->identifier, $record->profile, $newToken);

	return $newToken['access_token'];
}
