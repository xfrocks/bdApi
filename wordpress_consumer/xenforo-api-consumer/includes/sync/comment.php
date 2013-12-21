<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_wp_update_comment_count($postId, $new, $old)
{
	if ($new > $old)
	{
		$threadIds = get_post_meta($postId, XFAC_META_THREAD_IDS, true);
		if (empty($threadIds))
		{
			return;
		}
		$threadIds = unserialize($threadIds);

		$pushDate = intval(get_post_meta($postId, XFAC_META_PUSH_DATE, true));
		$comments = get_approved_comments($postId);
		$maxPushedCommentDateGmt = $pushDate;

		foreach ($comments as $comment)
		{
			$commentDateGmt = mysql2date('U', $comment->comment_date_gmt, false);
			if ($commentDateGmt > $pushDate)
			{
				// this comment hasn't been pushed yet, do it now
				$postIds = array();

				foreach ($threadIds as $threadId)
				{
					$xfPost = xfac_push_comment($threadId, $comment);
					if (!empty($xfPost))
					{
						$postIds[$threadId] = $xfPost['post']['post_id'];
					}
				}

				if (!empty($postIds))
				{
					update_comment_meta($comment->comment_ID, XFAC_META_POST_IDS, serialize($postIds));
					$maxPushedCommentDateGmt = max($maxPushedCommentDateGmt, $commentDateGmt);
				}
			}
		}

		if ($maxPushedCommentDateGmt > $pushDate)
		{
			update_post_meta($postId, XFAC_META_PUSH_DATE, $maxPushedCommentDateGmt);
		}
	}
}

add_action('wp_update_comment_count', 'xfac_wp_update_comment_count', 10, 3);

function xfac_push_comment($xfThreadId, $wfComment)
{
	$accessToken = xfac_user_getAccessToken($wfComment->user_id);

	if (empty($accessToken))
	{
		return false;
	}

	$root = get_option('xfac_root');
	$clientId = get_option('xfac_client_id');
	$clientSecret = get_option('xfac_client_secret');

	if (empty($root) OR empty($clientId) OR empty($clientSecret))
	{
		return false;
	}

	return xfac_api_postPost($root, $clientId, $clientSecret, $accessToken, $xfThreadId, $wfComment->comment_content);
}
