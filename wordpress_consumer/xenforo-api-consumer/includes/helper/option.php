<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_option_getWorkingMode()
{
	static $mode = false;

	if ($mode === false)
	{
		$mode = 'blog';

		if (is_multisite())
		{
			$plugins = get_site_option('active_sitewide_plugins');
			if (isset($plugins['xenforo-api-consumer/xenforo-api-consumer.php']))
			{
				// we should have used is_plugin_active_for_network
				// but that is only available in Dashboard...
				$mode = 'network';
			}
		}
	}

	return $mode;
}

function xfac_option_getConfig()
{
	static $config = null;

	if ($config === null)
	{
		$config = array();

		switch (xfac_option_getWorkingMode())
		{
			case 'network':
				$config['root'] = get_site_option('xfac_root');
				$config['clientId'] = get_site_option('xfac_client_id');
				$config['clientSecret'] = get_site_option('xfac_client_secret');
				break;
			case 'blog':
			default:
				$config['root'] = get_option('xfac_root');
				$config['clientId'] = get_option('xfac_client_id');
				$config['clientSecret'] = get_option('xfac_client_secret');
				break;
		}

		if (empty($config['root']) OR empty($config['clientId']) OR empty($config['clientSecret']))
		{
			$config = false;
		}
		else
		{
			$config['version'] = intval(get_option('xfac_version'));
		}
	}

	return $config;
}

function xfac_option_getMeta($config)
{
	if (empty($config))
	{
		return array();
	}

	$meta = get_option('xfac_meta');
	$rebuild = false;

	if (empty($meta) OR empty($meta['linkIndex']))
	{
		$rebuild = true;
	}
	else
	{
		foreach ($config as $configKey => $configValue)
		{
			if (empty($meta[$configKey]) OR $meta[$configKey] !== $configValue)
			{
				$rebuild = true;
				break;
			}
		}
	}

	if ($rebuild)
	{
		$meta = $config;

		$meta['linkIndex'] = xfac_api_getPublicLink($config, 'index');
		$meta['forums'] = array();

		if (!empty($meta['linkIndex']))
		{
			$meta['linkAlerts'] = xfac_api_getPublicLink($config, 'account/alerts');
			$meta['linkConversations'] = xfac_api_getPublicLink($config, 'conversations');
			$meta['linkLogin'] = xfac_api_getPublicLink($config, 'login');
			$meta['linkLoginLogin'] = xfac_api_getPublicLink($config, 'login/login');
			$meta['linkRegister'] = xfac_api_getPublicLink($config, 'register');

			$forums = xfac_api_getForums($config, '');
			if (!empty($forums['forums']))
			{
				$meta['forums'] = $forums['forums'];
			}
		}

		update_option('xfac_meta', $meta);
	}

	return $meta;
}
