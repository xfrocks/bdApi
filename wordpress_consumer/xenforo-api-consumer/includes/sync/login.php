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
		$records = xfac_user_getRecordsByUserId($wpUser->ID);
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

	$records = xfac_user_getRecordsByUserId($wpUser->ID);
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
		$records = xfac_user_getRecordsByUserId($wpUser->ID);
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

function xfac_authenticate($user, $username, $password)
{
	if (is_a($user, 'WP_User'))
	{
		return $user;
	}

	if (empty($username) OR empty($password))
	{
		return $user;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return $user;
	}

	$token = xfac_api_getAccessTokenFromUsernamePassword($config, $username, $password);
	if (empty($token))
	{
		return $user;
	}

	$me = xfac_api_getUsersMe($config, $token['access_token']);
	if (empty($me['user']))
	{
		return $user;
	}
	$xfUser = $me['user'];

	$wpUser = xfac_user_getUserByApiData($config['root'], $xfUser['user_id']);
	if (!empty($wpUser))
	{
		// yay, found an associated user!
		xfac_user_updateRecord($newUserId, $config['root'], $xfUser['user_id'], $xfUser, $token);

		return $wpUser;
	}

	$wpUserMatchingEmail = get_user_by('email', $xfUser['user_email']);
	if (!empty($wpUserMatchingEmail))
	{
		// this is not good, an user with matched email
		// this user will have to associate manually
		return $user;
	}

	if (!!get_option('users_can_register') OR !!get_option('xfac_bypass_users_can_register'))
	{
		// try to register if possible
		$newUserId = wp_create_user($xfUser['username'], wp_generate_password(), $xfUser['user_email']);
		if (!is_wp_error($newUserId))
		{
			xfac_user_updateRecord($newUserId, $config['root'], $xfUser['user_id'], $xfUser, $token);

			return new WP_User($newUserId);
		}
	}

	return $user;
}

if (!!get_option('xfac_sync_password'))
{
	add_filter('authenticate', 'xfac_authenticate', PHP_INT_MAX, 3);
}
