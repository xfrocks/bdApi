<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_transition_post_status($newStatus, $oldStatus, $post)
{
	if (!empty($GLOBALS['XFAC_SKIP_xfac_transition_post_status']))
	{
		return;
	}

	if ($newStatus == 'publish')
	{
		// we need to make sure our crons are scheduled
		xfac_setupCrons();

		$tagForumMappings = get_option('xfac_tag_forum_mappings');

		$forumIds = array();

		foreach ($tagForumMappings as $tagForumMapping)
		{
			if (!empty($tagForumMapping['term_id']))
			{
				if (is_object_in_term($post->ID, 'post_tag', $tagForumMapping['term_id']))
				{
					$forumIds[] = $tagForumMapping['forum_id'];
				}
			}
		}

		if (!empty($forumIds))
		{
			$accessToken = xfac_user_getAccessToken($post->post_author);

			if (!empty($accessToken))
			{
				$config = xfac_option_getConfig();

				if (!empty($config))
				{
					$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $post->ID);
					foreach (array_keys($forumIds) as $key)
					{
						foreach ($postSyncRecords as $postSyncRecord)
						{
							if (!empty($postSyncRecord->syncData['forumId']) AND $postSyncRecord->syncData['forumId'] == $forumIds[$key])
							{
								unset($forumIds[$key]);
							}
						}
					}

					foreach ($forumIds as $forumId)
					{
						$thread = xfac_api_postThread($config, $accessToken, $forumId, $post->post_title, $post->post_content);

						if (!empty($thread['thread']['thread_id']))
						{
							xfac_sync_updateRecord('', 'thread', $thread['thread']['thread_id'], $post->ID, 0, array(
								'forumId' => $forumId,
								'thread' => $thread['thread'],
								'direction' => 'push',
							));
						}
					}
				}
			}
		}
	}
}

add_action('transition_post_status', 'xfac_transition_post_status', 10, 3);

function xfac_syncPost_cron()
{
	$systemTags = get_terms('post_tag', array('hide_empty' => false));
	$mappedTags = array();

	$tagForumMappings = get_option('xfac_tag_forum_mappings');
	foreach ($tagForumMappings as $tagForumMapping)
	{
		if (!empty($tagForumMapping['forum_id']))
		{
			if (empty($mappedTags[$tagForumMapping['forum_id']]))
			{
				$mappedTags[$tagForumMapping['forum_id']] = array();
			}

			foreach ($systemTags as $systemTag)
			{
				if ($systemTag->term_id == $tagForumMapping['term_id'])
				{
					$mappedTags[$tagForumMapping['forum_id']][] = $systemTag->name;
				}
			}

		}
	}

	if (empty($mappedTags))
	{
		return;
	}

	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	foreach ($mappedTags as $forumId => $tagNames)
	{
		$page = 1;

		while (true)
		{
			$threads = xfac_api_getThreadsInForum($config, $forumId, $page);

			// increase page for next request
			$page++;

			if (empty($threads['threads']))
			{
				break;
			}

			$threadIds = array();
			foreach ($threads['threads'] as $thread)
			{
				$threadIds[] = $thread['thread_id'];
			}
			$syncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', $threadIds);

			foreach ($threads['threads'] as $thread)
			{
				$synced = false;

				foreach ($syncRecords as $syncRecord)
				{
					if ($syncRecord->provider_content_id == $thread['thread_id'])
					{
						$synced = true;

						if (!empty($syncRecord->syncData['direction']) AND $syncRecord->syncData['direction'] === 'pull')
						{
							// reach where we were pulling before
							// stop the foreach and the outside while too
							break 3;
						}
					}
				}

				if (!$synced)
				{
					$wfPostId = xfac_syncPost_pullPost($thread, $tagNames);
				}
			}
		}
	}
}

add_action('xfac_cron_hourly', 'xfac_syncPost_cron');

function xfac_syncPost_pullPost($thread, $tags)
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return 0;
	}
	
	$postAuthor = 0;
	$wfUserData = xfac_user_getUserDataByApiData($config['root'], $thread['creator_user_id']);
	if (empty($wfUserData))
	{
		return 0;
	}
	$postAuthor = $wfUserData->ID;

	$postDateGmt = gmdate('Y-m-d H:i:s', $thread['thread_create_date']);
	$postDate = get_date_from_gmt($postDateGmt);

	$wfPost = array(
		'post_author' => $postAuthor,
		'post_content' => $thread['first_post']['post_body'],
		'post_date' => $postDate,
		'post_date_gmt' => $postDateGmt,
		'post_status' => 'publish',
		'post_title' => $thread['thread_title'],
		'post_type' => 'post',
		'tags_input' => implode(', ', $tags),
	);

	$GLOBALS['XFAC_SKIP_xfac_transition_post_status'] = true;
	$wfPostId = wp_insert_post($wfPost);
	$GLOBALS['XFAC_SKIP_xfac_transition_post_status'] = false;

	if ($wfPostId > 0)
	{
		xfac_sync_updateRecord('', 'thread', $thread['thread_id'], $wfPostId, $thread['thread_create_date'], array(
			'thread' => $thread,
			'direction' => 'pull',
		));
	}

	return $wfPostId;
}
