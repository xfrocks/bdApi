<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_login_form()
{
	$root = get_option('xfac_root');
	$clientId = get_option('xfac_client_id');
	$clientSecret = get_option('xfac_client_secret');

	if (empty($root) OR empty($clientId) OR empty($clientSecret))
	{
		return;
	}

	$loginUrl = site_url('wp-login.php', 'login_post');
	$redirectTo = _xfac_getRedirectTo();

	$authenticateUrl = $loginUrl . (strpos($loginUrl, '?') !== false ? '&' : '?') . 'xfac=authorize';
	$authenticateUrl .= '&redirect_to=' . urlencode($redirectTo);

	$href = esc_url($authenticateUrl);
	$text = __('Login with XenForo', 'xenforo-api-consumer');

	echo <<<EOF
<div>
	<a href="$href">$text</a>
</div>
EOF;
}

add_action('login_form', 'xfac_login_form');

function xfac_login_init()
{
	if (empty($_REQUEST['xfac']))
	{
		return;
	}

	$root = get_option('xfac_root');
	$clientId = get_option('xfac_client_id');
	$clientSecret = get_option('xfac_client_secret');
	if (empty($root) OR empty($clientId) OR empty($clientSecret))
	{
		return;
	}

	$loginUrl = site_url('wp-login.php', 'login_post');
	$redirectTo = _xfac_getRedirectTo();

	$redirectBaseUrl = $loginUrl . (strpos($loginUrl, '?') !== false ? '&' : '?') . 'redirect_to=' . urlencode($redirectTo);
	$callbackUrl = $redirectBaseUrl . '&xfac=callback';

	switch ($_REQUEST['xfac'])
	{
		case 'callback':
			if (!empty($_REQUEST['code']))
			{
				$token = xfac_api_getAccessTokenFromCode($root, $clientId, $clientSecret, $_REQUEST['code'], $callbackUrl);

				if (empty($token))
				{
					wp_redirect($redirectBaseUrl . '&xfac_error=no_token');
					exit();
				}

				$me = xfac_api_getUsersMe($root, $clientId, $clientSecret, $token['access_token']);
				if (empty($me['user']))
				{
					wp_redirect($redirectBaseUrl . '&xfac_error=no_read_scope');
					exit();
				}
				$xfUser = $me['user'];

				$wfUser = xfac_user_getUserByApiData($root, $xfUser['user_id']);

				if (empty($wfUser))
				{
					// no user with the API data found
					// find user with matching email...
					if (!empty($xfUser['user_email']))
					{
						$wfUserEmail = get_user_by('email', $xfUser['user_email']);
						if (!empty($wfUserEmail))
						{
							// user with matching email found
							// TODO: check for existing auth record?
							$wfUser = $wfUserEmail;
						}
					}
				}

				if (empty($wfUser))
				{
					// no matching user found, register new
					$newUserId = register_new_user($xfUser['username'], $xfUser['user_email']);
					if (is_wp_error($newUserId))
					{
						wp_redirect($redirectBaseUrl . '&xfac_error=cannot_register');
						exit();
					}

					$wfUser = new WP_User($newUserId);
				}

				xfac_user_updateAuth($wfUser, $root, $xfUser['user_id'], $xfUser, $token);

				wp_set_auth_cookie($wfUser->ID, true);

				wp_redirect($redirectTo);
				exit();
			}
			break;
		case 'authorize':
		default:
			$authorizeUrl = xfac_api_getAuthorizeUrl($root, $clientId, $clientSecret, $callbackUrl);

			wp_redirect($authorizeUrl);
			exit();
	}

}

add_action('login_init', 'xfac_login_init');

function _xfac_getRedirectTo()
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
		$redirectTo = site_url();
	}

	return $redirectTo;
}
