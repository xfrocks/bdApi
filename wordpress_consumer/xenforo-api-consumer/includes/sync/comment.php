<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_wp_update_comment_count($postId, $new, $old)
{
	if (!empty($GLOBALS['XFAC_SKIP_xfac_wp_update_comment_count']))
	{
		return;
	}

	if ($new > $old)
	{
		$threadIds = array();
		$syncDate = array();

		$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $postId);
		foreach ($postSyncRecords as $postSyncRecord)
		{
			$threadIds[$postSyncRecord->syncData['forumId']] = $postSyncRecord->provider_content_id;
			$syncDate[$postSyncRecord->provider_content_id] = $postSyncRecord->sync_date;
		}
		if (empty($threadIds))
		{
			return;
		}

		$comments = get_approved_comments($postId);
		$newSyncDate = array();

		foreach ($comments as $comment)
		{
			$commentDateGmt = xfac_sync_mysqlDateToGmtTimestamp($comment->comment_date_gmt);
			foreach ($threadIds as $forumId => $threadId)
			{
				if ($commentDateGmt > $syncDate[$threadId])
				{
					// this comment hasn't been pushed yet, do it now
					$xfPost = xfac_syncComment_pushComment($threadId, $comment);

					if (!empty($xfPost['post']['post_id']))
					{
						xfac_sync_updateRecord('', 'post', $xfPost['post']['post_id'], $comment->comment_ID, 0, array(
							'forumId' => $forumId,
							'threadId' => $threadId,
							'post' => $xfPost['post'],
							'direction' => 'push',
						));

						$newSyncDate[$threadId] = true;
					}
				}
			}
		}

		if (!empty($newSyncDate))
		{
			foreach ($newSyncDate as $threadId => $tmp)
			{
				foreach ($postSyncRecords as $postSyncRecord)
				{
					if ($threadId == $postSyncRecord->provider_content_id)
					{
						xfac_sync_updateRecordDate($postSyncRecord);
					}
				}
			}
		}
	}
}

if (intval(get_option('xfac_sync_comment_wp_xf')) > 0)
{
	add_action('wp_update_comment_count', 'xfac_wp_update_comment_count', 10, 3);
}

function xfac_syncComment_pushComment($xfThreadId, $wpComment)
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

	return xfac_api_postPost($config, $accessToken, $xfThreadId, $wpComment->comment_content, $extraParams);
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

	while (true)
	{
		$xfPosts = xfac_api_getPostsInThread($config, $postSyncRecord->provider_content_id, $page, $accessToken);

		if ($page == 1 AND empty($xfPosts['subscription_callback']) AND !empty($xfPosts['_headerLinkHub']))
		{
			if (xfac_api_postSubscription($config, $accessToken, $xfPosts['_headerLinkHub']))
			{
				$postSyncRecord->syncData['subscribed'] = time();
				xfac_sync_updateRecord('', $postSyncRecord->provider_content_type, $postSyncRecord->provider_content_id, $postSyncRecord->sync_id, 0, $postSyncRecord->syncData);
			}
		}

		// increase page for next request
		$page++;

		if (empty($xfPosts['posts']))
		{
			break;
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

					if (!empty($commentSyncRecord->syncData['direction']) AND $commentSyncRecord->syncData['direction'] === 'pull')
					{
						// stop the foreach and the outside while too
						break 3;
					}
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

		if (empty($xfPosts['links']['next']))
		{
			// there is no next page, stop
			break;
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

	$GLOBALS['XFAC_SKIP_xfac_wp_update_comment_count'] = true;
	$commentId = wp_insert_comment($comment);
	$GLOBALS['XFAC_SKIP_xfac_wp_update_comment_count'] = false;

	if ($commentId > 0)
	{
		xfac_sync_updateRecord('', 'post', $xfPost['post_id'], $commentId, 0, array(
			'post' => $xfPost,
			'direction' => $direction,
		));
	}

	return $commentId;
}
