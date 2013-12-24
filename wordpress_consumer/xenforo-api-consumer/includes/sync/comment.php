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
			$commentDateGmt = mysql2date('U', $comment->comment_date_gmt, false);
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
							'post' => $xfPost,
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

add_action('wp_update_comment_count', 'xfac_wp_update_comment_count', 10, 3);

function xfac_syncComment_pushComment($xfThreadId, $wfComment)
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
