<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_options_init()
{

	$config = xfac_option_getConfig();
	$hourlyNext = wp_next_scheduled('xfac_cron_hourly');

	$xfGuestRecords = xfac_user_getRecordsByUserId(0);

	$tagForumMappings = get_option('xfac_tag_forum_mappings');
	if (!is_array($tagForumMappings))
	{
		$tagForumMappings = array();
	}

	$optionTopBarForums = get_option('xfac_top_bar_forums');
	if (!is_array($optionTopBarForums))
	{
		$optionTopBarForums = array();
	}

	$tags = get_terms('post_tag', array('hide_empty' => false));
	$forums = array();

	if (!empty($config))
	{
		$meta = xfac_option_getMeta($config);

		$apiForums = xfac_api_getForums($config);
		if (!empty($apiForums['forums']))
		{
			$forums = $apiForums['forums'];
		}

		$callbackUrl = admin_url('options-general.php?page=xfac&do=xfac_xf_guest_account');
		$authorizeUrl = xfac_api_getAuthorizeUrl($config, $callbackUrl);
	}

	require (xfac_template_locateTemplate('dashboard_options.php'));
}

function xfac_wpmu_options()
{
	$config = xfac_option_getConfig();
	$meta = xfac_option_getMeta($config);

	require (xfac_template_locateTemplate('dashboard_wpmu_options.php'));
}

add_action('wpmu_options', 'xfac_wpmu_options');

function xfac_update_wpmu_options()
{
	$options = array(
		'xfac_root',
		'xfac_client_id',
		'xfac_client_secret',
	);

	foreach ($options as $optionName)
	{
		if (!isset($_POST[$optionName]))
		{
			continue;
		}

		$optionValue = wp_unslash($_POST[$optionName]);
		update_site_option($optionName, $optionValue);
	}
}

add_action('update_wpmu_options', 'xfac_update_wpmu_options');

function xfac_dashboardOptions_admin_init()
{
	if (empty($_REQUEST['page']))
	{
		return;
	}
	if ($_REQUEST['page'] !== 'xfac')
	{
		return;
	}

	if (!empty($_REQUEST['cron']))
	{
		switch ($_REQUEST['cron'])
		{
			case 'hourly':
				do_action('xfac_cron_hourly');
				wp_redirect(admin_url('options-general.php?page=xfac&ran=hourly'));
				exit ;
		}
	}
	elseif (!empty($_REQUEST['do']))
	{
		switch ($_REQUEST['do'])
		{
			case 'xfac_meta':
				update_option('xfac_meta', array());
				wp_redirect(admin_url('options-general.php?page=xfac&done=xfac_meta'));
				break;
			case 'xfac_xf_guest_account':
				$config = xfac_option_getConfig();
				$callbackUrl = admin_url('options-general.php?page=xfac&do=xfac_xf_guest_account');

				if (empty($_REQUEST['code']))
				{
					wp_die('no_code');
				}
				$token = xfac_api_getAccessTokenFromCode($config, $_REQUEST['code'], $callbackUrl);

				if (empty($token))
				{
					wp_die('no_token');
				}
				$guest = xfac_api_getUsersMe($config, $token['access_token']);

				if (empty($guest['user']))
				{
					wp_die('no_xf_user');
				}
				xfac_user_updateRecord(0, $config['root'], $guest['user']['user_id'], $guest['user'], $token);

				$records = xfac_user_getRecordsByUserId(0);
				$record = reset($records);
				update_option('xfac_xf_guest_account', $record->id);

				wp_redirect(admin_url('options-general.php?page=xfac&done=xfac_xf_guest_account'));
				break;
		}
	}
}

add_action('admin_init', 'xfac_dashboardOptions_admin_init');
