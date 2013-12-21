<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_install()
{
	global $wpdb;

	$currentVersion = 3;
	$installedVersion = intval(get_option('xfac_version'));

	$tblAuth = $wpdb->prefix . 'xfac_auth';
	$tblSync = $wpdb->prefix . 'xfac_sync';

	if ($installedVersion < 1)
	{
		require_once (ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta('
			CREATE TABLE ' . $tblAuth . ' (
				id INT(11) NOT NULL AUTO_INCREMENT,
				user_id INT(11) NOT NULL,
				provider VARCHAR(50) NOT NULL,
				identifier VARCHAR(100) NOT NULL,
				profile MEDIUMBLOB,
				token MEDIUMBLOB,
				PRIMARY KEY (id),
				UNIQUE KEY `user_id` (`user_id`) 
			);
		');
	}

	if ($installedVersion < 2)
	{
		if (!$wpdb->get_col($wpdb->prepare('SHOW KEYS FROM ' . $tblAuth . ' WHERE key_name = %s', 'user_id')))
		{
			$wpdb->query('ALTER TABLE ' . $tblAuth . ' ADD UNIQUE KEY `user_id` (`user_id`);');
		}
	}

	if ($installedVersion < 3)
	{
		require_once (ABSPATH . 'wp-admin/includes/upgrade.php');

		dbDelta('
			CREATE TABLE ' . $tblSync . ' (
				provider VARCHAR(50) NOT NULL,
				provider_content_type VARCHAR(50) NOT NULL,
				provider_content_id VARCHAR(100) NOT NULL,
				sync_id INT(11) NOT NULL,
				sync_date INT(10) UNSIGNED NOT NULL,
				sync_data MEDIUMBLOB,
				PRIMARY KEY (provider, provider_content_type, provider_content_id, sync_id)
			);
		');
		
		xfac_setup_crons();
	}

	if ($installedVersion > 0)
	{
		update_option('xfac_version', $currentVersion);
	}
	else
	{
		add_option('xfac_version', $currentVersion);
	}
}

// TODO: drop this action? It is called within xfac_activate already
add_action('plugins_loaded', 'xfac_install');
