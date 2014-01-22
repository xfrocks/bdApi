<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_admin_init()
{
	if (xfac_option_getWorkingMode() === 'blog')
	{
		register_setting('xfac-settings', 'xfac_root');
		register_setting('xfac-settings', 'xfac_client_id');
		register_setting('xfac-settings', 'xfac_client_secret');
	}

	register_setting('xfac-settings', 'xfac_tag_forum_mappings');
	register_setting('xfac-settings', 'xfac_sync_post_wp_xf');
	register_setting('xfac-settings', 'xfac_sync_post_xf_wp');
	register_setting('xfac-settings', 'xfac_sync_post_xf_wp_publish');
	register_setting('xfac-settings', 'xfac_sync_comment_wp_xf');
	register_setting('xfac-settings', 'xfac_sync_comment_xf_wp');
}

add_action('admin_init', 'xfac_admin_init');

function xfac_admin_menu()
{
	add_options_page('XenForo API Consumer', 'XenForo API Consumer', 'manage_options', 'xfac', 'xfac_options_init');
}

add_action('admin_menu', 'xfac_admin_menu');

function xfac_plugin_action_links($links, $file)
{
	if ($file == 'xenforo-api-consumer/xenforo-api-consumer.php')
	{
		$settings_link = '<a href="options-general.php?page=xfac">' . __("Settings") . '</a>';

		array_unshift($links, $settings_link);
	}

	return $links;
}

add_filter('plugin_action_links', 'xfac_plugin_action_links', 10, 2);

function xfac_whitelist_options($whitelist_options)
{
	if (xfac_option_getWorkingMode() === 'blog')
	{
		$whitelist_options['xfac'][] = 'xfac_root';
		$whitelist_options['xfac'][] = 'xfac_client_id';
		$whitelist_options['xfac'][] = 'xfac_client_secret';
	}

	$whitelist_options['xfac'][] = 'xfac_tag_forum_mappings';
	$whitelist_options['xfac'][] = 'xfac_sync_post_wp_xf';
	$whitelist_options['xfac'][] = 'xfac_sync_post_xf_wp';
	$whitelist_options['xfac'][] = 'xfac_sync_post_xf_wp_publish';
	$whitelist_options['xfac'][] = 'xfac_sync_comment_wp_xf';
	$whitelist_options['xfac'][] = 'xfac_sync_comment_xf_wp';

	return $whitelist_options;
}

add_filter('whitelist_options', 'xfac_whitelist_options');
