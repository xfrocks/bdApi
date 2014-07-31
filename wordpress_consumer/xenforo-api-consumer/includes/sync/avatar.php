<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_get_avatar($avatar = '', $id_or_email, $size = 96, $default = '', $alt = '')
{
	if (is_numeric($id_or_email))
	{
		$wpUserId = (int)$id_or_email;
	}
	elseif (is_string($id_or_email) && ($user = get_user_by('email', $id_or_email)))
	{
		$wpUserId = $user->ID;
	}
	elseif (is_object($id_or_email) && !empty($id_or_email->user_id))
	{
		$wpUserId = (int)$id_or_email->user_id;
	}

	if (empty($wpUserId))
	{
		// cannot figure out the user id...
		return $avatar;
	}

	$apiRecords = xfac_user_getRecordsByUserId($wpUserId);
	if (empty($apiRecords))
	{
		// no api records
		return $avatar;
	}
	$apiRecord = reset($apiRecords);

	if (empty($apiRecord->profile['links']['avatar']))
	{
		// no avatar?
		return $avatar;
	}
	$avatar = $apiRecord->profile['links']['avatar'];

	$size = (int)$size;

	if (empty($alt))
	{
		$alt = get_the_author_meta('display_name', $wpUserId);
	}

	$author_class = is_author($wpUserId) ? ' current-author' : '';
	$avatar = "<img alt='" . esc_attr($alt) . "' src='" . esc_url($avatar) . "' class='avatar avatar-{$size}{$author_class} photo' height='{$size}' width='{$size}' />";

	return $avatar;
}

if (intval(get_option('xfac_sync_avatar_xf_wp')) > 0)
{
	add_filter('get_avatar', 'xfac_get_avatar', 10, 5);
}
