<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_login_redirect($redirectTo, $redirectToRequested, $wpUser)
{
	$config = xfac_option_getConfig();

	if (!defined('XFAC_SYNC_LOGIN_SKIP_REDIRECT') AND !empty($config) AND !empty($wpUser->ID))
	{
		$records = xfac_user_getApiRecordsByUserId($wpUser->ID);
		if (!empty($records))
		{
			$record = reset($records);

			$accessToken = xfac_user_getAccessTokenForRecord($record);
			$ott = xfac_api_generateOneTimeToken($config, $record->identifier, $accessToken);

			$redirectTo = xfac_api_getLoginLink($config, $ott, $redirectTo);
		}
	}

	return $redirectTo;
}

function xfac_allowed_redirect_hosts($hosts)
{
	$config = xfac_option_getConfig();
	if (!empty($config))
	{
		$rootParsed = parse_url($config['root']);
		if (!empty($rootParsed['host']))
		{
			$hosts[] = $rootParsed['host'];
		}
	}

	return $hosts;
}

function xfac_wp_logout()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// do nothing
		return;
	}

	$wpUser = wp_get_current_user();

	if (empty($wpUser->ID))
	{
		// hmm, how could guest perform log out?
		return;
	}

	$records = xfac_user_getApiRecordsByUserId($wpUser->ID);
	if (!empty($records))
	{
		$record = reset($records);

		$accessToken = xfac_user_getAccessTokenForRecord($record);
		$ott = xfac_api_generateOneTimeToken($config, $record->identifier, $accessToken);

		$redirectTo = !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : home_url();
		$newRedirectTo = xfac_api_getLogoutLink($config, $ott, $redirectTo);

		$_REQUEST['redirect_to'] = $newRedirectTo;
	}
}

function xfac_syncLogin_wp_enqueue_scripts()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// do nothing
		return;
	}

	$wpUser = wp_get_current_user();
	if ($wpUser->ID > 0)
	{
		// don't add ajax login for users
		return;
	}

	wp_enqueue_script('jquery');
	wp_enqueue_script('xfac-sdk', xfac_api_getSdkJsUrl($config));
	wp_enqueue_script('xfac-login.js', XFAC_PLUGIN_URL . '/js/login.js');
}

function xfac_syncLogin_wp_head()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// do nothing
		return;
	}

	$wpUser = wp_get_current_user();
	if ($wpUser->ID > 0)
	{
		// don't add ajax login for users
		return;
	}

	echo '<script>window.xfacClientId = "' . $config['clientId'] . '"</script>';
	echo '<script>window.xfacWpLogin = "' . site_url('wp-login.php?xfac=1') . '"</script>';
}

function xfac_edit_profile_url($url, $user, $scheme)
{
	$wpUser = wp_get_current_user();
	if ($user == $wpUser->ID AND $wpUser->has_cap('subscriber'))
	{
		$records = xfac_user_getApiRecordsByUserId($wpUser->ID);
		if (!empty($records))
		{
			$record = reset($records);
			if (!empty($record->profile['links']['permalink']))
			{
				$url = $record->profile['links']['permalink'];
			}
		}
	}

	return $url;
}

if (!!get_option('xfac_sync_login'))
{
	add_filter('login_redirect', 'xfac_login_redirect', 10, 3);

	add_filter('allowed_redirect_hosts', 'xfac_allowed_redirect_hosts');
	add_action('wp_logout', 'xfac_wp_logout');

	add_action('wp_enqueue_scripts', 'xfac_syncLogin_wp_enqueue_scripts');
	add_action('wp_head', 'xfac_syncLogin_wp_head');

	add_filter('edit_profile_url', 'xfac_edit_profile_url', 10, 3);
}
