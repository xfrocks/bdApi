<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_login_form()
{
	if (!!get_option('xfac_sync_password'))
	{
		// password sync is enabled, do not inert our link
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	$loginUrl = site_url('wp-login.php', 'login_post');
	$redirectTo = _xfac_login_getRedirectTo();

	$authenticateUrl = $loginUrl . (strpos($loginUrl, '?') !== false ? '&' : '?') . 'xfac=authorize';
	$authenticateUrl .= '&redirect_to=' . urlencode($redirectTo);

	$href = esc_url($authenticateUrl);
	$text = __('Login with XenForo', 'xenforo-api-consumer');

	echo "<div>\n\t<a href=\"$href\">{$text}</a>\n</div>\n";
}

add_action('login_form', 'xfac_login_form');

function xfac_login_init()
{
	if (empty($_REQUEST['xfac']))
	{
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	$loginUrl = site_url('wp-login.php', 'login_post');
	$redirectTo = _xfac_login_getRedirectTo();
	$redirectToRequested = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';

	$redirectBaseUrl = $loginUrl . (strpos($loginUrl, '?') !== false ? '&' : '?') . 'redirect_to=' . urlencode($redirectTo);
	$callbackUrl = $redirectBaseUrl . '&xfac=callback';

	$token = false;
	$associateConfirmed = false;

	switch ($_REQUEST['xfac'])
	{
		case 'callback':
			define('XFAC_SYNC_LOGIN_SKIP_REDIRECT', 1);
			if (!empty($_REQUEST['code']))
			{
				$token = xfac_api_getAccessTokenFromCode($config, $_REQUEST['code'], $callbackUrl);
			}
			break;
		case 'associate':
			define('XFAC_SYNC_LOGIN_SKIP_REDIRECT', 1);
			if (empty($_REQUEST['refresh_token']))
			{
				wp_redirect($redirectBaseUrl . '&xfac_error=no_refresh_token');
				exit();
			}
			if (empty($_REQUEST['scope']))
			{
				wp_redirect($redirectBaseUrl . '&xfac_error=no_scope');
				exit();
			}
			if (empty($_REQUEST['xf_user']) OR !is_array($_REQUEST['xf_user']))
			{
				wp_redirect($redirectBaseUrl . '&xfac_error=no_request_xf_user');
				exit();
			}

			if (empty($_REQUEST['user_login']))
			{
				wp_redirect($redirectBaseUrl . '&xfac_error=no_user_login');
				exit();
			}
			$wpUserForAssociate = get_user_by('login', $_REQUEST['user_login']);

			if (empty($_REQUEST['pwd']))
			{
				_xfac_login_renderAssociateForm($wpUserForAssociate, $_REQUEST['xf_user'], $_REQUEST['refresh_token'], $_REQUEST['scope'], $redirectTo);
				exit();
			}
			$password = $_REQUEST['pwd'];

			$authenticatedUser = wp_authenticate($wpUserForAssociate->user_login, $password);
			if (is_wp_error($authenticatedUser) OR $authenticatedUser->ID != $wpUserForAssociate->ID)
			{
				_xfac_login_renderAssociateForm($wpUserForAssociate, $_REQUEST['xf_user'], $_REQUEST['refresh_token'], $_REQUEST['scope'], $redirectTo);
				exit();
			}

			$token = xfac_api_getAccessTokenFromRefreshToken($config, $_REQUEST['refresh_token'], $_REQUEST['scope']);
			$associateConfirmed = $wpUserForAssociate->ID;
			break;
		case 'authorize':
		default:
			$authorizeUrl = xfac_api_getAuthorizeUrl($config, $callbackUrl);

			// wp_redirect($authorizeUrl);
			// cannot use wp_redirect because wp_sanitize_redirect changes our url
			// issues: it removes basic auth (http://user:password@path)
			// TODO: find better way to do this
			header("Location: $authorizeUrl", true, 302);
			exit();
	}

	if (empty($token))
	{
		wp_redirect($redirectBaseUrl . '&xfac_error=no_token');
		exit();
	}
	if (empty($token['scope']))
	{
		wp_redirect($redirectBaseUrl . '&xfac_error=no_scope');
		exit();
	}

	$me = xfac_api_getUsersMe($config, $token['access_token']);
	if (empty($me['user']))
	{
		wp_redirect($redirectBaseUrl . '&xfac_error=no_xf_user');
		exit();
	}
	$xfUser = $me['user'];

	$wpUser = xfac_user_getUserByApiData($config['root'], $xfUser['user_id']);

	if (empty($wpUser))
	{
		// no user with the API data found
		// find user with matching email...
		if (!empty($xfUser['user_email']))
		{
			$wpUserMatchingEmail = get_user_by('email', $xfUser['user_email']);
			if (!empty($wpUserMatchingEmail))
			{
				// user with matching email found
				if (!$associateConfirmed)
				{
					_xfac_login_renderAssociateForm($wpUserMatchingEmail, $xfUser, $token['refresh_token'], $token['scope'], $redirectTo);
					exit();
				}
				elseif ($associateConfirmed == $wpUserMatchingEmail->ID)
				{
					// association has been confirmed
					$wpUser = $wpUserMatchingEmail;
				}
			}
		}
	}

	if (empty($wpUser))
	{
		$currentWpUser = wp_get_current_user();

		if (!empty($currentWpUser) AND $currentWpUser->ID > 0)
		{
			// a user is currently logged in, try to associate now
			if (!$associateConfirmed)
			{
				_xfac_login_renderAssociateForm($currentWpUser, $xfUser, $token['refresh_token'], $token['scope'], $redirectTo);
				exit();
			}
			elseif ($associateConfirmed == $currentWpUser->ID)
			{
				// association has been confirmed
				$wpUser = $currentWpUser;

				if ($redirectTo == admin_url('profile.php'))
				{
					// redirect target is profile.php page, it will alter it a bit
					$redirectTo = admin_url('profile.php?xfac=associated');
				}
			}
		}
		else
		{
			// no matching user found, try to register
			if (!!get_option('users_can_register') OR !!get_option('xfac_bypass_users_can_register'))
			{
				$newUserId = wp_create_user($xfUser['username'], wp_generate_password(), $xfUser['user_email']);
				if (is_wp_error($newUserId))
				{
					wp_redirect($redirectBaseUrl . '&xfac_error=register_error&message=' . urlencode($newUserId->get_error_message()));
					exit();
				}

				$wpUser = new WP_User($newUserId);
			}
			else
			{
				wp_redirect($redirectBaseUrl . '&xfac_error=users_cannot_register');
				exit();
			}
		}
	}

	if (!empty($wpUser))
	{
		xfac_user_updateRecord($wpUser->ID, $config['root'], $xfUser['user_id'], $xfUser, $token);

		wp_set_auth_cookie($wpUser->ID, true);

		$redirectToFiltered = apply_filters('login_redirect', $redirectTo, $redirectToRequested, $wpUser);
		wp_redirect($redirectToFiltered);
		exit();
	}
}

add_action('login_init', 'xfac_login_init');

function _xfac_login_getRedirectTo()
{
	if (!empty($_REQUEST['redirect_to']))
	{
		return $_REQUEST['redirect_to'];
	}

	$redirectTo = 'http';
	if (isset($_SERVER['HTTPS']) AND ($_SERVER['HTTPS'] == 'on'))
	{
		$redirectTo .= 's';
	}
	$redirectTo .= '://';
	if ($_SERVER['SERVER_PORT'] != '80')
	{
		$redirectTo .= $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
	}
	else
	{
		$redirectTo .= $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	if (strpos($redirectTo, 'wp-login.php') !== false)
	{
		$redirectTo = home_url();
	}

	return $redirectTo;
}

function _xfac_login_renderAssociateForm(WP_User $wpUser, array $xfUser, $refreshToken, $scope, $redirectTo)
{
	$title = __('Associate Account', 'xenforo-api-consumer');
	$message = sprintf(__('Enter your password to associate the account "%1$s" with your profile.', 'xenforo-api-consumer'), $xfUser['username']);

	require (xfac_template_locateTemplate('login_associate.php'));
}
