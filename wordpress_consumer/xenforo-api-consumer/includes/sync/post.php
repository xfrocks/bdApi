<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_save_post($postId, WP_Post $post, $update)
{
	if (!empty($GLOBALS['XFAC_SKIP_xfac_save_post']))
	{
		return;
	}

	if ($post->post_status == 'publish')
	{
		$tagForumMappings = get_option('xfac_tag_forum_mappings');
		if (empty($tagForumMappings))
		{
			return;
		}

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
					$existingSyncRecords = array();
					foreach (array_keys($forumIds) as $key)
					{
						foreach ($postSyncRecords as $postSyncRecord)
						{
							if (!empty($postSyncRecord->syncData['forumId']) AND $postSyncRecord->syncData['forumId'] == $forumIds[$key])
							{
								unset($forumIds[$key]);
								$existingSyncRecords[] = $postSyncRecord;
							}
						}
					}

					$postBody = _xfac_syncPost_getPostBody($post);

					foreach ($forumIds as $forumId)
					{
						$thread = xfac_api_postThread($config, $accessToken, $forumId, $post->post_title, $postBody);

						if (!empty($thread['thread']['thread_id']))
						{
							xfac_sync_updateRecord('', 'thread', $thread['thread']['thread_id'], $post->ID, 0, array(
								'forumId' => $forumId,
								'thread' => $thread['thread'],
								'direction' => 'push',
							));
						}
					}

					foreach ($existingSyncRecords as $existingSyncRecord)
					{
						if (empty($existingSyncRecord->syncData['thread']['first_post']['post_id']))
						{
							// no information about first post to update
							continue;
						}

						$xfPost = xfac_api_putPost($config, $accessToken, $existingSyncRecord->syncData['thread']['first_post']['post_id'], $postBody, array('thread_title' => $post->post_title));

						if (!empty($xfPost['post_id']))
						{
							$syncData = $existingSyncRecord->syncData;
							$syncData['direction'] = 'push';
							$syncData['thread']['first_post'] = $xfPost;

							xfac_sync_updateRecord('', 'thread', $xfPost['thread_id'], $post->ID, 0, $syncData);
						}
					}
				}
			}
		}
	}
}

if (intval(get_option('xfac_sync_post_wp_xf')) > 0)
{
	add_action('save_post', 'xfac_save_post', 10, 3);
}

function xfac_syncPost_cron()
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	$mappedTags = xfac_syncPost_getMappedTags();
	if (empty($mappedTags))
	{
		return;
	}

	foreach ($mappedTags as $forumId => $tagNames)
	{
		// sync sticky threads first
		$stickyThreads = xfac_api_getThreadsInForum($config, $forumId, 1, '', 'sticky=1');
		if (!empty($stickyThreads['threads']))
		{
			$threadIds = array();
			foreach ($stickyThreads['threads'] as $thread)
			{
				$threadIds[] = $thread['thread_id'];
			}
			$syncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', $threadIds);

			foreach ($stickyThreads['threads'] as $thread)
			{
				$synced = false;

				foreach ($syncRecords as $syncRecord)
				{
					if ($syncRecord->provider_content_id == $thread['thread_id'])
					{
						$synced = true;
					}
				}

				if (!$synced)
				{
					$wpPostId = xfac_syncPost_pullPost($thread, $tagNames);
				}
			}
		}

		// now start syncing normal threads
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

						if (!empty($syncRecord->syncData['direction']) AND $syncRecord->syncData['direction'] === 'pull' AND empty($syncRecord->syncData['sticky']))
						{
							// reach where we were pulling before
							// stop the foreach and the outside while too
							break 3;
						}
					}
				}

				if (!$synced)
				{
					$wpPostId = xfac_syncPost_pullPost($thread, $tagNames);
				}
			}

			if (empty($threads['links']['next']))
			{
				// there is no next page, stop
				break;
			}
		}
	}
}

function xfac_update_option_tag_forum_mappings($option, $oldValue, $newValue)
{
	if ($option === 'xfac_tag_forum_mappings')
	{
		$forumIds = array();
		foreach ($newValue as $tagForumMapping)
		{
			if (!empty($tagForumMapping['term_id']) AND !empty($tagForumMapping['forum_id']))
			{
				$forumIds[] = intval($tagForumMapping['forum_id']);
			}
		}

		$accessToken = xfac_user_getSystemAccessToken();

		if (!empty($accessToken))
		{
			$config = xfac_option_getConfig();
			if (!empty($config))
			{
				$forumFollowed = xfac_api_getForumFollowed($config, $accessToken);
				$followedForumIds = array();

				foreach ($forumFollowed['forums'] as $forumFollowedOne)
				{
					$followedForumIds[] = intval($forumFollowedOne['forum_id']);
				}
				foreach (array_diff($forumIds, $followedForumIds) as $forumId)
				{
					// follow the forum to get thread notification
					xfac_api_postForumFollower($config, $accessToken, $forumId);
				}
				foreach (array_diff($followedForumIds, $forumIds) as $forumId)
				{
					// unfollow the forum to save server resources
					xfac_api_deleteForumFollower($config, $accessToken, $forumId);
				}

				// make sure we subscribed for notification callback
				$notifications = xfac_api_getNotifications($config, $accessToken);
				if (empty($notifications['subscription_callback']) AND !empty($notifications['_headerLinkHub']))
				{
					xfac_api_postSubscription($config, $accessToken, $notifications['_headerLinkHub']);
				}
			}
		}
	}
}

if (intval(get_option('xfac_sync_post_xf_wp')) > 0)
{
	add_action('xfac_cron_hourly', 'xfac_syncPost_cron');
	add_action('update_option', 'xfac_update_option_tag_forum_mappings', 10, 3);
}

function xfac_syncPost_getMappedTags($forumId = 0)
{
	$mappedTags = array();

	$systemTags = get_terms('post_tag', array('hide_empty' => false));
	if (empty($systemTags))
	{
		return $mappedTags;
	}

	$tagForumMappings = get_option('xfac_tag_forum_mappings');
	if (empty($tagForumMappings))
	{
		return $mappedTags;
	}

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

	if ($forumId === 0)
	{
		// get tags for all forums
		return $mappedTags;
	}
	if (isset($mappedTags[$forumId]))
	{
		return $mappedTags[$forumId];
	}
	else
	{
		return array();
	}
}

function xfac_syncPost_pullPost($thread, $tags, $direction = 'pull')
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return 0;
	}

	$postAuthor = 0;
	$wpUserData = xfac_user_getUserDataByApiData($config['root'], $thread['creator_user_id']);
	if (empty($wpUserData))
	{
		return 0;
	}
	$postAuthor = $wpUserData->ID;

	$wpUser = new WP_User($wpUserData);
	$postTypeObj = get_post_type_object('post');
	if (empty($postTypeObj))
	{
		// no post type object?!
		return 0;
	}
	if (!$wpUser->has_cap($postTypeObj->cap->create_posts))
	{
		// no permission to create posts
		return 0;
	}

	$postDateGmt = gmdate('Y-m-d H:i:s', $thread['thread_create_date']);
	$postDate = get_date_from_gmt($postDateGmt);

	$postStatus = 'draft';
	if (intval(get_option('xfac_sync_post_xf_wp_publish')) > 0)
	{
		$postStatus = 'publish';
	}

	$postContent = xfac_api_filterHtmlFromXenForo($thread['first_post']['post_body_html']);

	$wpPost = array(
		'post_author' => $postAuthor,
		'post_content' => $postContent,
		'post_date' => $postDate,
		'post_date_gmt' => $postDateGmt,
		'post_status' => $postStatus,
		'post_title' => $thread['thread_title'],
		'post_type' => 'post',
		'tags_input' => implode(', ', $tags),
	);

	$GLOBALS['XFAC_SKIP_xfac_save_post'] = true;
	$wpPostId = wp_insert_post($wpPost);
	$GLOBALS['XFAC_SKIP_xfac_save_post'] = false;

	if ($wpPostId > 0)
	{
		$subscribed = 0;

		if (intval(get_option('xfac_sync_comment_xf_wp')) > 0)
		{
			$accessToken = xfac_user_getAccessToken($wpUser->ID);
			if (!empty($accessToken))
			{
				$xfPosts = xfac_api_getPostsInThread($config, $thread['thread_id'], 1, $accessToken);
				if (empty($xfPosts['subscription_callback']) AND !empty($xfPosts['_headerLinkHub']))
				{
					if (xfac_api_postSubscription($config, $accessToken, $xfPosts['_headerLinkHub']))
					{
						$subscribed = time();
					}
				}
			}
		}

		xfac_sync_updateRecord('', 'thread', $thread['thread_id'], $wpPostId, $thread['thread_create_date'], array(
			'forumId' => $thread['forum_id'],
			'thread' => $thread,
			'direction' => $direction,
			'sticky' => !empty($thread['thread_is_sticky']),
			'subscribed' => $subscribed,
		));
	}

	return $wpPostId;
}

function _xfac_syncPost_getPostBody($post)
{
	if (!!get_option('xfac_sync_post_wp_xf_excerpt'))
	{
		// this method is implemented with ideas from get_the_excerpt()
		$text = $post->post_excerpt;

		if (empty($text))
		{
			$text = $post->post_content;

			$text = strip_shortcodes($text);

			$text = apply_filters('the_content', $text);
			$text = str_replace(']]>', ']]&gt;', $text);

			$excerptLength = apply_filters('excerpt_length', 55);

			$excerptMore = apply_filters('excerpt_more', ' ' . '[&hellip;]');
			$text = wp_trim_words($text, $excerptLength, $excerptMore);
		}

		$text = apply_filters('wp_trim_excerpt', $text, $raw_excerpt);
	}
	else
	{
		$text = $post->post_content;

		$text = apply_filters('the_content', $text);
		$text = str_replace(']]>', ']]&gt;', $text);
	}

	// fix paragraph spacing from WordPress to XenForo
	$text = str_replace('</p>', '</p><br />', $text);

	if (!!get_option('xfac_sync_post_wp_xf_link'))
	{
		$text .= '<br /><a href="' . get_permalink($post->ID) . '">' . __('Read the whole post here.', 'xenforo-api-consumer') . '</a>';
	}

	return $text;
}
