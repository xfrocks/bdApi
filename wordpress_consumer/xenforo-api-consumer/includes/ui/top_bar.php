<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_admin_bar_forums_menu($wpAdminBar)
{
	if (is_admin())
	{
		// don't add menu in Dashboard
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// don't add menu for site without configuration
		return;
	}

	$optionTopBarForums = get_option('xfac_top_bar_forums');
	if (!is_array($optionTopBarForums))
	{
		$optionTopBarForums = array();
	}

	$meta = xfac_option_getMeta($config);

	if (in_array(0, $optionTopBarForums))
	{
		$wpAdminBar->add_menu(array(
			'id' => 'xfac-forums',
			'title' => __('Forums', 'xenforo-api-consumer'),
			'href' => 'http://blah',
		));

		foreach ($optionTopBarForums as $forumId)
		{
			$forum = false;

			foreach ($meta['forums'] as $_forum)
			{
				if ($_forum['forum_id'] == $forumId)
				{
					$forum = $_forum;
				}
			}

			if (empty($forum))
			{
				continue;
			}

			$wpAdminBar->add_menu(array(
				'parent' => 'xfac-forums',
				'id' => 'xfac-forum-' . $forum['forum_id'],
				'title' => $forum['forum_title'],
				'href' => $forum['links']['permalink'],
			));
		}
	}
}

function xfac_admin_bar_jscount_menu($wpAdminBar)
{
	if (is_admin())
	{
		// don't add menu in Dashboard
		return;
	}

	$wpUser = wp_get_current_user();
	if (empty($wpUser->ID))
	{
		// don't add menu for guests
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// don't add menu for site without configuration
		return;
	}

	$records = xfac_user_getApiRecordsByUserId($wpUser->ID);
	if (empty($records))
	{
		// don't add menu for not-connected users
		return;
	}
	$record = reset($records);

	$meta = xfac_option_getMeta($config);

	$accessToken = xfac_user_getAccessTokenForRecord($record);
	$ott = xfac_api_generateOneTimeToken($config, $record->profile['user_id'], $accessToken);
	$html = '<script>window.xfacOneTimeToken = "' . $ott . '";</script>';

	if (!!get_option('xfac_top_bar_notifications') AND !empty($meta['linkAlerts']))
	{
		$notificationsTitle = __('Alerts', 'xenforo-api-consumer');
		$notificationsTitle .= ' <span id="xfacNotificationCount" class="xfacJsCount"></span>';

		$wpAdminBar->add_menu(array(
			'id' => 'xfac-notifications',
			'title' => $notificationsTitle,
			'href' => $meta['linkAlerts'],
			'meta' => array('html' => $html),
		));
	}

	if (!!get_option('xfac_top_bar_conversations') AND !empty($meta['linkConversations']))
	{
		$conversationTitle = __('Conversations', 'xenforo-api-consumer');
		$conversationTitle .= ' <span id="xfacConversationCount" class="xfacJsCount"></span>';

		$wpAdminBar->add_menu(array(
			'id' => 'xfac-conversations',
			'title' => $conversationTitle,
			'href' => $meta['linkConversations'],
			'meta' => array('html' => $html),
		));
	}
}

function xfac_add_admin_bar_menus()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		// don't add menu for site without configuration
		return;
	}

	add_action('admin_bar_menu', 'xfac_admin_bar_forums_menu', 30);

	if (!!get_option('xfac_top_bar_notifications') OR !!get_option('xfac_top_bar_conversations'))
	{
		wp_enqueue_script('jquery');
		wp_enqueue_script('xfac-sdk', xfac_api_getSdkJsUrl($config));
		wp_enqueue_script('xfac-top_bar.js', XFAC_PLUGIN_URL . '/js/top_bar.js');
		wp_enqueue_style('xfac-top_bar.css', XFAC_PLUGIN_URL . '/css/top_bar.css');

		add_action('admin_bar_menu', 'xfac_admin_bar_jscount_menu', 30);
	}
}

add_action('add_admin_bar_menus', 'xfac_add_admin_bar_menus');
