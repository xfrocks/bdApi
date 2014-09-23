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

	$config = xfac_option_getConfig();
	$meta = xfac_option_getMeta($config);
	if (!empty($meta['linkIndex']))
	{
		register_setting('xfac-settings', 'xfac_tag_forum_mappings');
		register_setting('xfac-settings', 'xfac_sync_post_wp_xf');
		register_setting('xfac-settings', 'xfac_sync_post_wp_xf_excerpt');
		register_setting('xfac-settings', 'xfac_sync_post_wp_xf_link');
		register_setting('xfac-settings', 'xfac_sync_post_xf_wp');
		register_setting('xfac-settings', 'xfac_sync_post_xf_wp_publish');
		register_setting('xfac-settings', 'xfac_sync_comment_wp_xf');
		register_setting('xfac-settings', 'xfac_sync_comment_wp_xf_as_guest');
		register_setting('xfac-settings', 'xfac_sync_comment_xf_wp');
		register_setting('xfac-settings', 'xfac_sync_comment_xf_wp_as_guest');
		register_setting('xfac-settings', 'xfac_sync_avatar_xf_wp');

		register_setting('xfac-settings', 'xfac_bypass_users_can_register');
		register_setting('xfac-settings', 'xfac_sync_password');
		register_setting('xfac-settings', 'xfac_sync_login');
		register_setting('xfac-settings', 'xfac_sync_user_wp_xf');

		register_setting('xfac-settings', 'xfac_top_bar_forums');
		register_setting('xfac-settings', 'xfac_top_bar_notifications');
		register_setting('xfac-settings', 'xfac_top_bar_conversations');
		register_setting('xfac-settings', 'xfac_top_bar_replace');
		register_setting('xfac-settings', 'xfac_top_bar_always');

		register_setting('xfac-settings', 'xfac_xf_guest_account');
		register_setting('xfac-settings', 'xfac_xf_admin_account');
	}
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

	$config = xfac_option_getConfig();
	$meta = xfac_option_getMeta($config);
	if (!empty($meta['linkIndex']))
	{
		$whitelist_options['xfac'][] = 'xfac_tag_forum_mappings';
		$whitelist_options['xfac'][] = 'xfac_sync_post_wp_xf';
		$whitelist_options['xfac'][] = 'xfac_sync_post_wp_xf_excerpt';
		$whitelist_options['xfac'][] = 'xfac_sync_post_wp_xf_link';
		$whitelist_options['xfac'][] = 'xfac_sync_post_xf_wp';
		$whitelist_options['xfac'][] = 'xfac_sync_post_xf_wp_publish';
		$whitelist_options['xfac'][] = 'xfac_sync_comment_wp_xf';
		$whitelist_options['xfac'][] = 'xfac_sync_comment_wp_xf_as_guest';
		$whitelist_options['xfac'][] = 'xfac_sync_comment_xf_wp';
		$whitelist_options['xfac'][] = 'xfac_sync_comment_xf_wp_as_guest';
		$whitelist_options['xfac'][] = 'xfac_sync_avatar_xf_wp';

		$whitelist_options['xfac'][] = 'xfac_bypass_users_can_register';
		$whitelist_options['xfac'][] = 'xfac_sync_password';
		$whitelist_options['xfac'][] = 'xfac_sync_login';
		$whitelist_options['xfac'][] = 'xfac_sync_user_wp_xf';

		$whitelist_options['xfac'][] = 'xfac_top_bar_forums';
		$whitelist_options['xfac'][] = 'xfac_top_bar_notifications';
		$whitelist_options['xfac'][] = 'xfac_top_bar_conversations';
		$whitelist_options['xfac'][] = 'xfac_top_bar_replace';
		$whitelist_options['xfac'][] = 'xfac_top_bar_always';

		$whitelist_options['xfac'][] = 'xfac_xf_guest_account';
		$whitelist_options['xfac'][] = 'xfac_xf_admin_account';
	}

	return $whitelist_options;
}

add_filter('whitelist_options', 'xfac_whitelist_options');
