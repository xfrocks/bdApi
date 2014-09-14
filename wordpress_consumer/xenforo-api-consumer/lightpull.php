<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

define('XFAC_LIGHTPULL_ROOT', 'http://local.dev/lightpull/xenforo/api');
define('XFAC_LIGHTPULL_CLIENT_ID', 'lightpull');
define('XFAC_LIGHTPULL_CLIENT_SECRET', 'lightpull');

function xfac_lightpull_admin_init()
{
	if (empty($_REQUEST['page']) OR $_REQUEST['page'] !== 'xfac')
	{
		return;
	}
	if (empty($_REQUEST['do']) OR $_REQUEST['do'] !== 'lightpull_wizard')
	{
		return;
	}

	$config = xfac_lightpull_getConfig();
	$callbackUrl = admin_url('options-general.php?page=xfac&do=lightpull_wizard&step=2');

	if (empty($_REQUEST['step']))
	{
		// first step, redirect to our authorization page
		$authorizeUrl = xfac_api_getAuthorizeUrl($config, $callbackUrl);
		wp_redirect($authorizeUrl);
		exit ;
	}

	if (empty($_REQUEST['code']))
	{
		wp_die('no_code');
	}
	$token = xfac_api_getAccessTokenFromCode($config, $_REQUEST['code'], $callbackUrl);

	if (empty($token))
	{
		wp_die('no_token');
	}

	$clients = xfac_lightpull_api_getClients($config, $token['access_token']);
	$updatedOptions = false;

	if (!empty($clients['clients']))
	{
		foreach ($clients['clients'] as $existingClient)
		{
			if ($existingClient['redirect_uri'] == home_url())
			{
				update_option('xfac_root', $config['root']);
				update_option('xfac_client_id', $existingClient['client_id']);
				update_option('xfac_client_secret', $existingClient['client_secret']);
				$updatedOptions = true;
			}
		}
	}

	if (!$updatedOptions AND !empty($clients['can_create_client']))
	{
		$newClient = xfac_lightpull_api_postClients($config, $token['access_token'], get_option('blogname'), get_option('blogdescription'), home_url());
		if (!empty($newClient['client']))
		{
			update_option('xfac_root', $config['root']);
			update_option('xfac_client_id', $newClient['client']['client_id']);
			update_option('xfac_client_secret', $newClient['client']['client_secret']);
			$updatedOptions = true;
		}
	}

	wp_redirect(admin_url('options-general.php?page=xfac'));
	exit ;
}

add_action('admin_init', 'xfac_lightpull_admin_init');

function xfac_lightpull_api_getClients($config, $accessToken)
{
	$body = file_get_contents(call_user_func_array('sprintf', array(
		'%s/index.php?lightpull/clients/&oauth_token=%s',
		rtrim($config['root'], '/'),
		rawurlencode($accessToken)
	)));

	$parts = @json_decode($body, true);

	if (isset($parts['clients']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_lightpull_api_postClients($config, $accessToken, $name, $description, $redirectUri)
{
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, call_user_func_array('sprintf', array(
		'%s/index.php?lightpull/clients/',
		rtrim($config['root'], '/')
	)));

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
		'oauth_token' => $accessToken,
		'name' => $name,
		'description' => $description,
		'redirect_uri' => $redirectUri,
	)));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$body = curl_exec($ch);
	curl_close($ch);

	$parts = @json_decode($body, true);

	if (isset($parts['client']))
	{
		return $parts;
	}
	else
	{
		return _xfac_api_getFailedResponse($parts);
	}
}

function xfac_lightpull_getConfig()
{
	return array(
		'root' => XFAC_LIGHTPULL_ROOT,
		'clientId' => XFAC_LIGHTPULL_CLIENT_ID,
		'clientSecret' => XFAC_LIGHTPULL_CLIENT_SECRET
	);
}
