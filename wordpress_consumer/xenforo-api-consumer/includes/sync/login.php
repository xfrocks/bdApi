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
		foreach ($records as $record)
		{
			$accessToken = xfac_user_getAccessTokenForRecord($record);
			$ott = xfac_api_generateOneTimeToken($config, $record->identifier, $accessToken);
			$redirectTo = xfac_api_getRedirectTo();
			$newRedirectTo = xfac_api_getLogoutLink($config, $ott, $redirectTo);

			$_REQUEST['redirect_to'] = $newRedirectTo;
		}
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
	function xfac_login_redirect_add_filter()
	{
		// put this in a function to trigger it later (s2member)
		add_filter('login_redirect', 'xfac_login_redirect', 10, 3);
	}
	xfac_login_redirect_add_filter();

	// s2member removes all filters so we have to add back ours afterwards
	add_action('ws_plugin__s2member_after_remove_login_redirect_filters', 'xfac_login_redirect_add_filter');

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
		xfac_syncLogin_syncRole($config, $wpUser, $xfUser);
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
			$newUser = new WP_User($newUserId);

			xfac_syncLogin_syncRole($config, $newUser, $xfUser);
			xfac_user_updateRecord($newUserId, $config['root'], $xfUser['user_id'], $xfUser, $token);

			return $newUser;
		}
	}

	return $user;
}

if (!!get_option('xfac_sync_password'))
{
	add_filter('authenticate', 'xfac_authenticate', PHP_INT_MAX, 3);
}

function xfac_authenticate_syncUserWpXf($user, $username, $password)
{
	if (!is_a($user, 'WP_User'))
	{
		return $user;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return $user;
	}

	$records = xfac_user_getRecordsByUserId($user->ID);
	if (!empty($records))
	{
		return $user;
	}

	$username = $user->user_login;
	$atPos = strpos($username, '@');
	if ($atPos !== false)
	{
		// XenForo does not accept username in the email format
		// TODO: extra verification to make sure it is an address
		$username = substr($username, $atPos);
	}

	$result = xfac_api_postUser($config, $user->user_email, $username, $password);
	if (!empty($result))
	{
		// yay! new account has been created in XenForo
		$xfUser = $result['user'];
		$token = $result['token'];

		if (!isset($xfUser['user_email']))
		{
			// for some reason, user_email is not populated
			// we have to call another API request to get it
			// this is required to have all vital information regarding user
			$me = xfac_api_getUsersMe($config, $token['access_token']);
			if (!empty($me['user']))
			{
				$xfUser = $me['user'];
			}
		}

		xfac_syncLogin_syncRole($config, $user, $xfUser, false);
		xfac_user_updateRecord($user->ID, $config['root'], $xfUser['user_id'], $xfUser, $token);
	}
	else
	{
		$errors = xfac_api_getLastErrors();
		if (!empty($errors['username']))
		{
			// special case, a XenForo account with same username has already existed
			// TODO: improve this, there are other kind of username errors actually
			$token = xfac_api_getAccessTokenFromUsernamePassword($config, $username, $password);
			if (!empty($token))
			{
				$me = xfac_api_getUsersMe($config, $token['access_token']);
				if (!empty($me['user']))
				{
					$xfUser = $me['user'];
					xfac_syncLogin_syncRole($config, $user, $xfUser);
					xfac_user_updateRecord($user->ID, $config['root'], $xfUser['user_id'], $xfUser, $token);
				}
			}
		}
	}

	return $user;
}

if (!!get_option('xfac_sync_user_wp_xf'))
{
	add_filter('authenticate', 'xfac_authenticate_syncUserWpXf', PHP_INT_MAX - 1, 3);
}

function xfac_set_user_role($wpUserId, $newRole, $oldRoles)
{
	if (!empty($GLOBALS['XFAC_SKIP_xfac_set_user_role']))
	{
		return;
	}

	$config = xfac_option_getConfig();
	$accessToken = xfac_user_getAccessToken($wpUserId);
	if (empty($accessToken))
	{
		return;
	}

	$me = xfac_api_getUsersMe($config, $accessToken);
	if (empty($me['user']))
	{
		return;
	}
	$xfUser = $me['user'];

	$wpUser = new WP_User($wpUserId);

	xfac_syncLogin_syncRole($config, $wpUser, $xfUser, false);
}

if (!!get_option('xfac_sync_role_wp_xf'))
{
	add_action('set_user_role', 'xfac_set_user_role', 10, 3);
}

function xfac_syncLogin_syncRole($config, WP_User $wpUser, array $xfUser, $xfToWp = true)
{
	$meta = xfac_option_getMeta($config);
	if (empty($meta['userGroups']))
	{
		return false;
	}

	$syncRoleOption = get_option('xfac_sync_role');
	if (empty($syncRoleOption))
	{
		return false;
	}

	if ($xfToWp)
	{
		// good
	}
	elseif (!get_option('xfac_sync_role_wp_xf'))
	{
		// requested for WordPress to XenForo sync
		// but it is not enabled
		return false;
	}

	if ($xfToWp)
	{
		$currentRoles = $wpUser->roles;

		$targetRoles = array();
		if (!empty($xfUser['user_groups']))
		{
			foreach ($xfUser['user_groups'] as $xfUserGroup)
			{
				foreach ($syncRoleOption as $optionRoleName => $optionUserGroupId)
				{
					if ($xfUserGroup['user_group_id'] == $optionUserGroupId)
					{
						$targetRoles[] = $optionRoleName;
					}
				}
			}
		}

		foreach ($currentRoles as $currentRole)
		{
			if (isset($syncRoleOption[$currentRole]) AND $syncRoleOption[$currentRole] == -1)
			{
				// put do not sync roles into target roles directly
				// if the current role is high level, it will be kept
				// otherwise user level will just go up
				$targetRoles[] = $currentRole;
			}
		}

		if (!empty($currentRoles) AND !empty($targetRoles))
		{
			// TODO: improve this
			// we put a safe guard against unexpected error here
			// and do not sync if one of the arrays is empty
			// they should not be right? Right!?
			$newRole = _xfac_syncLogin_syncRole_getHighestLevelRole($targetRoles);

			$XFAC_SKIP_xfac_set_user_role_before = !empty($GLOBALS['XFAC_SKIP_xfac_set_user_role']);
			$GLOBALS['XFAC_SKIP_xfac_set_user_role'] = true;
			$wpUser->set_role($newRole);
			$GLOBALS['XFAC_SKIP_xfac_set_user_role'] = $XFAC_SKIP_xfac_set_user_role_before;
		}
	}
	else
	{
		$currentPrimaryGroupId = 0;
		$currentGroupIds = array();
		if (!empty($xfUser['user_groups']))
		{
			foreach ($xfUser['user_groups'] as $xfUserGroup)
			{
				$currentGroupIds[] = intval($xfUserGroup['user_group_id']);

				if (!empty($xfUserGroup['is_primary_group']))
				{
					$currentPrimaryGroupId = intval($xfUserGroup['user_group_id']);
				}
			}
		}
		asort($currentGroupIds);

		$targetGroupIds = array();
		$optionGroupIds = array();
		_xfac_syncLogin_syncRole_getHighestLevelRole($wpUser->roles, $wpUserLevel);
		foreach ($syncRoleOption as $optionRoleName => $optionGroupId)
		{
			$optionGroupId = intval($optionGroupId);

			if ($optionGroupId > 0)
			{
				$optionGroupIds[] = $optionGroupId;

				$optionLevel = false;
				_xfac_syncLogin_syncRole_getHighestLevelRole(array($optionRoleName), $optionLevel);

				if ($optionLevel <= $wpUserLevel)
				{
					$targetGroupIds[] = $optionGroupId;
				}
			}
		}
		foreach ($currentGroupIds as $currentGroupId)
		{
			if (!in_array($currentGroupId, $optionGroupIds, true))
			{
				// some group is not configured at all, keep them untouched
				$targetGroupIds[] = $currentGroupId;
			}
		}
		asort($targetGroupIds);

		if (!empty($targetGroupIds) AND serialize($currentGroupIds) !== serialize($targetGroupIds))
		{
			if (in_array($currentPrimaryGroupId, $targetGroupIds, true))
			{
				$newPrimaryGroupId = $currentPrimaryGroupId;
			}
			else
			{
				$newPrimaryGroupId = array_shift($targetGroupIds);
			}

			$newSecondaryGroupIds = array();
			foreach ($targetGroupIds as $groupId)
			{
				if ($groupId !== $newPrimaryGroupId)
				{
					$newSecondaryGroupIds[] = $groupId;
				}
			}

			$accessToken = xfac_user_getAdminAccessToken($config);
			xfac_api_postUserGroups($config, $accessToken, $xfUser['user_id'], $newPrimaryGroupId, $newSecondaryGroupIds);
		}
	}
}

function _xfac_syncLogin_syncRole_getHighestLevelRole(array $roles, &$levelMax = false)
{
	global $wp_roles;
	$highestLevelRole = '';

	foreach ($wp_roles->roles as $roleName => $roleInfo)
	{
		if (in_array($roleName, $roles))
		{
			foreach ($roleInfo['capabilities'] as $cap => $boolean)
			{
				if (preg_match('/^level_(\d+)$/i', $cap, $matches))
				{
					$level = intval($matches[1]);
					if ($levelMax === false OR $level > $levelMax)
					{
						$levelMax = $level;
						$highestLevelRole = $roleName;
					}
				}
			}
		}
	}

	return $highestLevelRole;
}
