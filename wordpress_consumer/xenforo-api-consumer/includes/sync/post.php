<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_transition_post_status($newStatus, $oldStatus, $post)
{
	if ($newStatus == 'publish')
	{
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
			$accessToken = xfac_user_getAccessToken(wp_get_current_user());

			if (!empty($accessToken))
			{
				$root = get_option('xfac_root');
				$clientId = get_option('xfac_client_id');
				$clientSecret = get_option('xfac_client_secret');

				if (!empty($root) AND !empty($clientId) AND !empty($clientSecret))
				{
					$threadIds = array();

					foreach ($forumIds as $forumId)
					{
						$thread = xfac_api_postThread($root, $clientId, $clientSecret, $accessToken, $forumId, $post->post_title, $post->post_content);

						if (!empty($thread['thread']['thread_id']))
						{
							$threadIds[$forumId] = $thread['thread']['thread_id'];
						}
					}

					if (!empty($threadIds))
					{
						update_post_meta($post->ID, XFAC_META_THREAD_IDS, serialize($threadIds));
					}
				}
			}
		}
	}
}

add_action('transition_post_status', 'xfac_transition_post_status', 10, 3);
