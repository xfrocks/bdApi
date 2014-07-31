<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_show_user_profile($wpUser)
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	$apiRecords = xfac_user_getRecordsByUserId($wpUser->ID);

	$connectUrl = site_url('wp-login.php?xfac=authorize&redirect_to=' . rawurlencode(admin_url('profile.php')), 'login_post');

	require (xfac_template_locateTemplate('dashboard_profile.php'));
}

add_action('show_user_profile', 'xfac_show_user_profile');

function xfac_dashboardProfile_admin_init()
{
	if (!defined('IS_PROFILE_PAGE'))
	{
		return;
	}

	if (empty($_REQUEST['xfac']))
	{
		return;
	}

	switch ($_REQUEST['xfac'])
	{
		case 'disconnect':
			if (empty($_REQUEST['id']))
			{
				return;
			}

			$wpUser = wp_get_current_user();
			if (empty($wpUser))
			{
				// huh?!
				return;
			}

			$apiRecords = xfac_user_getRecordsByUserId($wpUser->ID);
			if (empty($apiRecords))
			{
				return;
			}

			$requestedRecord = false;
			foreach ($apiRecords as $apiRecord)
			{
				if ($apiRecord->id == $_REQUEST['id'])
				{
					$requestedRecord = $apiRecord;
				}
			}
			if (empty($requestedRecord))
			{
				return;
			}

			xfac_user_deleteRecord($requestedRecord);
			wp_redirect('profile.php?xfac=disconnected');
			exit();
			break;
	}
}

add_action('admin_init', 'xfac_dashboardProfile_admin_init');
