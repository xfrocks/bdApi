<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_login_form()
{
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

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	$loginUrl = site_url('wp-login.php', 'login_post');
	$redirectTo = _xfac_login_getRedirectTo();

	$redirectBaseUrl = $loginUrl . (strpos($loginUrl, '?') !== false ? '&' : '?') . 'redirect_to=' . urlencode($redirectTo);
	$callbackUrl = $redirectBaseUrl . '&xfac=callback';

	$token = false;
	$associateConfirmed = false;

	switch ($_REQUEST['xfac'])
	{
		case 'callback':
			if (!empty($_REQUEST['code']))
			{
				$token = xfac_api_getAccessTokenFromCode($config, $_REQUEST['code'], $callbackUrl);
			}
			break;
		case 'associate':
			$wpUser = wp_get_current_user();
			if (empty($wpUser))
			{
				wp_redirect($redirectBaseUrl . '&xfac_error=no_user');
				exit();
			}

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

			if (empty($_REQUEST['pwd']))
			{
				_xfac_login_renderAssociateForm($wpUser, $_REQUEST['xf_user'], $_REQUEST['refresh_token'], $_REQUEST['scope'], $redirectTo);
				exit();
			}
			$password = $_REQUEST['pwd'];

			$authenticatedUser = apply_filters('authenticate', null, $wpUser->user_login, $password);
			if (empty($authenticatedUser->ID) OR $authenticatedUser->ID != $wpUser->ID)
			{
				_xfac_login_renderAssociateForm($wpUser, $_REQUEST['xf_user'], $_REQUEST['refresh_token'], $_REQUEST['scope'], $redirectTo);
				exit();
			}

			$token = xfac_api_getAccessTokenFromRefreshToken($config, $_REQUEST['refresh_token'], $_REQUEST['scope']);
			$associateConfirmed = true;
			break;
		case 'authorize':
		default:
			$authorizeUrl = xfac_api_getAuthorizeUrl($config, $callbackUrl);

			wp_redirect($authorizeUrl);
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

	$wfUser = xfac_user_getUserByApiData($config['root'], $xfUser['user_id']);

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
		$currentWfUser = wp_get_current_user();

		if (!empty($currentWfUser) AND $currentWfUser->ID > 0)
		{
			// a user is currently logged in, try to associate now
			if (!$associateConfirmed)
			{
				_xfac_login_renderAssociateForm($currentWfUser, $xfUser, $token['refresh_token'], $token['scope'], $redirectTo);
				exit();
			}
			else
			{
				// association has been confirmed
				$wfUser = $currentWfUser;

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
			if (intval(get_option('users_can_register')))
			{
				$newUserId = register_new_user($xfUser['username'], $xfUser['user_email']);
				if (is_wp_error($newUserId))
				{
					wp_redirect($redirectBaseUrl . '&xfac_error=register_error&message=' . urlencode($newUserId->get_error_message()));
					exit();
				}

				$wfUser = new WP_User($newUserId);
			}
			else
			{
				wp_redirect($redirectBaseUrl . '&xfac_error=users_cannot_register');
				exit();
			}
		}
	}

	if (!empty($wfUser))
	{
		xfac_user_updateAuth($wfUser->ID, $config['root'], $xfUser['user_id'], $xfUser, $token);

		wp_set_auth_cookie($wfUser->ID, true);

		wp_redirect($redirectTo);
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
	$message =  sprintf(__('Enter your password to associate the account "%1$s" with your profile.', 'xenforo-api-consumer'), $xfUser['username']);

	login_header($title, '<p class="message">' . $message . '</p>');
?>

<form id="associateform" action="<?php echo esc_url(site_url('wp-login.php?xfac=associate', 'login_post')); ?>" method="post">
	<p>
		<label for="user_login" >
			<?php _e('Username', 'xenforo-api-consumer') ?><br />
			<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr($wpUser->user_login); ?>" size="20" disabled="disabled" />
 		</label>
	</p>
	<p>
		<label for="user_pass">
			<?php _e('Password', 'xenforo-api-consumer') ?><br />
			<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" />
		</label>
	</p>

	<input type="hidden" name="xf_user[username]" value="<?php echo esc_attr($xfUser['username']) ?>" />
	<input type="hidden" name="refresh_token" value="<?php echo esc_attr($refreshToken) ?>" />
	<input type="hidden" name="scope" value="<?php echo esc_attr($scope) ?>" />
	<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo) ?>" />

	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large"
			value="<?php esc_attr_e('Associate Account', 'xenforo-api-consumer'); ?>" />
	</p>
</form>

<?php
	login_footer('user_login');
}
