<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_user_getApiRecordsByUserId($wfUserId)
{
	global $wpdb;

	$tblAuth = xfac_getTableAuth();

	$records = $wpdb->get_results($wpdb->prepare("
		SELECT *
		FROM {$tblAuth}
		WHERE user_id = %d
	", $wfUserId));

	foreach ($records as &$record)
	{
		$record->profile = unserialize($record->profile);
		$record->token = unserialize($record->token);
	}

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

function xfac_user_updateAuth($wfUserId, $root, $xfUserId, array $xfUser, array $token)
{
	global $wpdb;

	$tblAuth = xfac_getTableAuth();
	$provider = '';

	$wpdb->query($wpdb->prepare("
		REPLACE INTO {$tblAuth}
		(user_id, provider, identifier, profile, token)
		VALUES (%d, %s, %s, %s, %s)
	", $wfUserId, $provider, $xfUserId, serialize($xfUser), serialize($token)));
}

function xfac_user_deleteAuthById($authId)
{
	global $wpdb;

	$tblAuth = xfac_getTableAuth();

	return $wpdb->delete($tblAuth, array('id' => $authId));
}

function xfac_user_getAccessToken($wfUserId)
{
	global $wpdb;

	$tblAuth = xfac_getTableAuth();

	$auth = $wpdb->get_row($wpdb->prepare("
		SELECT *
		FROM {$tblAuth}
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

	xfac_user_updateAuth($wfUserId, $config['root'], $auth->identifier, unserialize($auth->profile), $newToken);

	return $newToken['access_token'];
}
