<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_wp_insert_comment($commentId, $comment)
{
	return _xfac_save_comment($comment);
}

function xfac_edit_comment($commentId)
{
	$GLOBALS['XFAC_xfac_edit_comment_' . $commentId] = true;

	return _xfac_save_comment(get_comment($commentId));
}

function xfac_transition_comment_status($newStatus, $oldStatus, $comment)
{
	if (!empty($GLOBALS['XFAC_xfac_edit_comment_' . $comment->comment_ID]))
	{
		// avoid calling save comment twice for the same comment
		return;
	}

	return _xfac_save_comment($comment);
}

if (intval(get_option('xfac_sync_comment_wp_xf')) > 0)
{
	add_action('wp_insert_comment', 'xfac_wp_insert_comment', 10, 2);
	add_action('edit_comment', 'xfac_edit_comment');
	add_action('transition_comment_status', 'xfac_transition_comment_status', 10, 3);
}

function _xfac_save_comment($comment)
{
	if (!empty($GLOBALS['XFAC_SKIP_xfac_save_comment']))
	{
		return;
	}

	$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $comment->comment_post_ID);
	$commentSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'post', $comment->comment_ID);

	foreach ($postSyncRecords as $postSyncRecord)
	{
		$commentSyncRecord = null;
		foreach ($commentSyncRecords as $_commentSyncRecord)
		{
			if (!empty($_commentSyncRecord->syncData['post']['thread_id']) AND $_commentSyncRecord->syncData['post']['thread_id'] == $postSyncRecord->provider_content_id)
			{
				$commentSyncRecord = $_commentSyncRecord;
			}
		}

		$xfPost = xfac_syncComment_pushComment($comment, $postSyncRecord, $commentSyncRecord);
	}
}

function xfac_syncComment_pushComment($wpComment, $postSyncRecord, $commentSyncRecord = null)
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return false;
	}

	$accessToken = false;
	$extraParams = array();

	if ($wpComment->user_id > 0)
	{
		$accessToken = xfac_user_getAccessToken($wpComment->user_id);
	}

	if (empty($accessToken) AND intval(get_option('xfac_sync_comment_wp_xf_as_guest')) > 0)
	{
		if (intval(get_option('xfac_xf_guest_account')) > 0)
		{
			// use pre-configured guest account
			$accessToken = xfac_user_getAccessToken(0);
		}
		else
		{
			// use one time token for guest
			$accessToken = xfac_api_generateOneTimeToken($config);
			$extraParams['guestUsername'] = $wpComment->comment_author;
		}
	}

	if (empty($accessToken))
	{
		return false;
	}

	$commentContent = $wpComment->comment_content;
	if (empty($commentSyncRecord))
	{
		$xfPost = xfac_api_postPost($config, $accessToken, $postSyncRecord->provider_content_id, $commentContent, $extraParams);
	}
	elseif ($wpComment->comment_approved)
	{
		$xfPost = xfac_api_putPost($config, $accessToken, $commentSyncRecord->provider_content_id, $commentContent, $extraParams);
	}
	else
	{
		$xfPost = xfac_api_deletePost($config, $accessToken, $commentSyncRecord->provider_content_id);
	}

	if (!empty($xfPost['post']['post_id']))
	{
		if (!empty($commentSyncRecord))
		{
			xfac_sync_updateRecordDate($commentSyncRecord);
		}
		else
		{
			xfac_sync_updateRecord('', 'post', $xfPost['post']['post_id'], $wpComment->comment_ID, 0, array(
				'post' => $xfPost['post'],
				'direction' => 'push',
			));
		}
	}
	else
	{
		if (!empty($commentSyncRecord))
		{
			xfac_sync_deleteRecord($commentSyncRecord);
		}
	}

	xfac_sync_updateRecordDate($postSyncRecord);

	return $xfPost;
}

function xfac_syncComment_cron()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return false;
	}

	$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndRecent('', 'thread');

	foreach ($postSyncRecords as $postSyncRecord)
	{
		xfac_syncComment_processPostSyncRecord($config, $postSyncRecord);
	}
}

if (intval(get_option('xfac_sync_comment_xf_wp')) > 0)
{
	add_action('xfac_cron_hourly', 'xfac_syncComment_cron');
}

function xfac_syncComment_processPostSyncRecord($config, $postSyncRecord)
{
	$page = 1;
	$pulledSomething = false;

	if (time() - $postSyncRecord->sync_date < 60)
	{
		// do not try to sync every minute...
		return false;
	}

	if (!empty($postSyncRecord->syncData['subscribed']))
	{
		if (time() - $postSyncRecord->sync_date < 86400)
		{
			// do not try to sync every day with subscribed thread
			return false;
		}
	}

	$wpUserData = xfac_user_getUserDataByApiData($config['root'], $postSyncRecord->syncData['thread']['creator_user_id']);
	$accessToken = xfac_user_getAccessToken($wpUserData->ID);

	$xfPosts = xfac_api_getPostsInThread($config, $postSyncRecord->provider_content_id, $accessToken);

	if (empty($xfPosts['subscription_callback']) AND !empty($xfPosts['_headerLinkHub']))
	{
		if (xfac_api_postSubscription($config, $accessToken, $xfPosts['_headerLinkHub']))
		{
			$postSyncRecord->syncData['subscribed'] = array(
				'hub' => $xfPosts['_headerLinkHub'],
				'time' => time(),
			);
			xfac_sync_updateRecord('', $postSyncRecord->provider_content_type, $postSyncRecord->provider_content_id, $postSyncRecord->sync_id, 0, $postSyncRecord->syncData);
		}
	}

	if (empty($xfPosts['posts']))
	{
		return false;
	}

	$xfPostIds = array();
	foreach ($xfPosts['posts'] as $xfPost)
	{
		$xfPostIds[] = $xfPost['post_id'];
	}
	$commentSyncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'post', $xfPostIds);

	foreach ($xfPosts['posts'] as $xfPost)
	{
		if (!empty($xfPost['post_is_first_post']))
		{
			// do not pull first post
			continue;
		}

		$synced = false;

		foreach ($commentSyncRecords as $commentSyncRecord)
		{
			if ($commentSyncRecord->provider_content_id == $xfPost['post_id'])
			{
				$synced = true;
			}
		}

		if (!$synced)
		{
			$commentId = xfac_syncComment_pullComment($config, $xfPost, $postSyncRecord->sync_id);

			if ($commentId > 0)
			{
				$pulledSomething = true;
			}
		}
	}

	if ($pulledSomething)
	{
		xfac_sync_updateRecordDate($postSyncRecord);
	}

	return $pulledSomething;
}

function xfac_syncComment_pullComment($config, $xfPost, $wpPostId, $direction = 'pull')
{
	$wpDisplayName = false;
	$wpUserEmail = false;
	$wpUserUrl = '';
	$wpUserId = 0;
	$wpUserData = xfac_user_getUserDataByApiData($config['root'], $xfPost['poster_user_id']);
	if (empty($wpUserData))
	{
		if (intval(get_option('xfac_sync_comment_xf_wp_as_guest')) == 0)
		{
			return 0;
		}

		// no wordpress user found but as_guest option is enabled
		// sync as guest now
		$wpDisplayName = $xfPost['poster_username'];
		$wpUserEmail = sprintf('%s-%d@xenforo-api.com', $xfPost['poster_username'], $xfPost['poster_user_id']);
	}
	else
	{
		$wpDisplayName = $wpUserData->display_name;
		$wpUserEmail = $wpUserData->user_email;
		$wpUserUrl = $wpUserData->user_url;
		$wpUserId = $wpUserData->ID;
	}

	$commentDateGmt = gmdate('Y-m-d H:i:s', $xfPost['post_create_date']);
	$commentDate = get_date_from_gmt($commentDateGmt);

	$commentContent = xfac_api_filterHtmlFromXenForo($xfPost['post_body_html']);

	$comment = array(
		'comment_post_ID' => $wpPostId,
		'comment_author' => $wpDisplayName,
		'comment_author_email' => $wpUserEmail,
		'comment_author_url' => $wpUserUrl,
		'comment_content' => $commentContent,
		'user_id' => $wpUserId,
		'comment_date_gmt' => $commentDateGmt,
		'comment_date' => $commentDate,
		'comment_approved' => 1,
	);

	$XFAC_SKIP_xfac_save_comment_before = !empty($GLOBALS['XFAC_SKIP_xfac_save_comment']);
	$GLOBALS['XFAC_SKIP_xfac_save_comment'] = true;
	$commentId = wp_insert_comment($comment);
	$GLOBALS['XFAC_SKIP_xfac_save_comment'] = $XFAC_SKIP_xfac_save_comment_before;

	if ($commentId > 0)
	{
		xfac_sync_updateRecord('', 'post', $xfPost['post_id'], $commentId, 0, array(
			'post' => $xfPost,
			'direction' => $direction,
		));
	}

	return $commentId;
}
