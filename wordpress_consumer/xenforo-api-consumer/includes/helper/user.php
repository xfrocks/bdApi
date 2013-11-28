<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_get_user_by_api_data($root, $xfUserId)
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

	$user = new WP_User;
	$user->init($userdata);

	return $user;
}

function xfac_update_user_auth(WP_User $wfUser, $root, $xfUserId, array $xfUser, array $token)
{
	global $wpdb;

	$provider = '';

	$wpdb->query($wpdb->prepare("
		REPLACE INTO {$wpdb->prefix}xfac_auth
		(user_id, provider, identifier, profile, token)
		VALUES (%d, %s, %s, %s, %s)
	", $wfUser->ID, $provider, $xfUserId, serialize($xfUser), serialize($token)));
}
