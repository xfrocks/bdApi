<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_subscription_handleIntentVerification(array $params)
{
	if (empty($params['client_id']))
	{
		// unable to determine hub authorized client
		header('HTTP/1.0 404 Not Found');
		return false;
	}
	$config = xfac_option_getConfig();
	if (empty($config['clientId']))
	{
		// no client configured, should not accept subscription
		header('HTTP/1.0 404 Not Found');
		return false;
	}
	if ($config['clientId'] !== $params['client_id'])
	{
		// client mis-matched
		header('HTTP/1.0 401 Unauthorized');
		return false;
	}

	// TODO: verify $params['hub_topic']?

	echo $params['hub_challenge'];
	return true;
}

function xfac_subscription_handleCallback(array $json)
{
	$config = xfac_option_getConfig();
	if (empty($config['clientId']))
	{
		return false;
	}

	$xfThreadIds = array();
	$xfPostIds = array();
	$xfThreadIdsSynced = array();

	// phrase 1: preparation
	foreach ($json as &$pingRef)
	{
		if (empty($pingRef['client_id']) OR $pingRef['client_id'] != $config['clientId'])
		{
			continue;
		}
		if (empty($pingRef['topic']))
		{
			continue;
		}
		$parts = explode('_', $pingRef['topic']);
		$pingRef['topic_id'] = array_pop($parts);
		$pingRef['topic_type'] = implode('_', $parts);

		switch ($pingRef['topic_type'])
		{
			case 'thread_post':
				$xfThreadIds[] = $pingRef['topic_id'];
				$xfPostIds[] = $pingRef['object_data'];
				break;
		}
	}

	// phrase 2: fetch sync records
	if (!empty($xfPostIds))
	{
		$postSyncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', $xfThreadIds);
	}
	if (!empty($xfPostIds))
	{
		$commentSyncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'post', $xfPostIds);
	}

	// phrase 3: sync data
	foreach ($json as &$pingRef)
	{
		if (empty($pingRef['topic_type']))
		{
			continue;
		}

		switch ($pingRef['topic_type'])
		{
			case 'thread_post':
				if (_xfac_subscription_handleCallback_threadPost($config, $pingRef, $postSyncRecords, $commentSyncRecords))
				{
					$xfThreadIdsSynced[] = $pingRef['topic_id'];
				}
				break;
		}
	}

	// phrase 3: update sync record
	foreach ($postSyncRecords as $postSyncRecord)
	{
		if (in_array($postSyncRecord->provider_content_id, $xfThreadIdsSynced))
		{
			xfac_sync_updateRecordDate($postSyncRecord);
		}
	}
}

function _xfac_subscription_handleCallback_threadPost($config, array $ping, array $postSyncRecords, array $commentSyncRecords)
{
	$postSyncRecord = null;
	$commentSyncRecord = null;

	foreach ($postSyncRecords as $_postSyncRecord)
	{
		if ($_postSyncRecord->provider_content_id == $ping['topic_id'])
		{
			$postSyncRecord = $_postSyncRecord;
		}
	}
	if (empty($postSyncRecord))
	{
		return false;
	}

	foreach ($commentSyncRecords as $_commentSyncRecord)
	{
		if ($_commentSyncRecord->provider_content_id == $ping['object_data'])
		{
			$commentSyncRecord = $_commentSyncRecord;
		}
	}

	$wpUserData = xfac_user_getUserDataByApiData($config['root'], $postSyncRecord->syncData['thread']['creator_user_id']);
	$accessToken = xfac_user_getAccessToken($wpUserData->ID);
	$xfPost = xfac_api_getPost($config, $ping['object_data'], $accessToken);

	if (empty($commentSyncRecord))
	{
		if ($ping['action'] == 'insert' AND !empty($xfPost['post']))
		{
			if (empty($xfPost['post']['post_is_first_post']))
			{
				// new XenForo post, create a new comment
				return xfac_syncComment_pullComment($config, $xfPost['post'], $postSyncRecord->sync_id, 'subscription') > 0;
			}
		}
		elseif ($ping['action'] == 'update' AND !empty($xfPost['post']))
		{
			if (!empty($xfPost['post']['post_is_first_post']))
			{
				// editing the first post from XenForo
				$postContent = xfac_api_filterHtmlFromXenForo($xfPost['post']['post_body_html']);

				// remove the link back
				$wfPostLink = get_permalink($postSyncRecord->sync_id);
				$postContent = preg_replace('#<a href="' . preg_quote($wfPostLink, '#') . '"[^>]*>[^<]+</a>$#', '', $postContent);

				$GLOBALS['XFAC_SKIP_xfac_save_post'] = true;
				$postUpdated = wp_update_post(array(
					'ID' => $postSyncRecord->sync_id,
					'post_content' => $postContent,
				));
				$GLOBALS['XFAC_SKIP_xfac_save_post'] = false;

				return is_int($postUpdated) AND $postUpdated > 0;
			}
			else
			{
				// we missed the XenForo post or something, try to create a new comment now
				return xfac_syncComment_pullComment($config, $xfPost['post'], $postSyncRecord->sync_id, 'subscription') > 0;
			}
		}
	}
	else
	{
		if ($ping['action'] == 'delete' AND (empty($xfPost['post']) OR !empty($xfPost['post']['post_is_deleted'])))
		{
			return wp_update_comment(array(
				'comment_ID' => $commentSyncRecord->sync_id,
				'comment_content' => $commentContent,
				'comment_approved' => 0,
			)) == 1;
		}
		elseif (($ping['action'] == 'insert' OR $ping['action'] == 'update') AND !empty($xfPost['post']))
		{
			$commentContent = xfac_api_filterHtmlFromXenForo($xfPost['post']['post_body_html']);

			return wp_update_comment(array(
				'comment_ID' => $commentSyncRecord->sync_id,
				'comment_content' => $commentContent,
				'comment_approved' => 1,
			)) == 1;
		}
	}

	return false;
}

function xfac_do_parse_request($bool, $wp, $extra_query_vars)
{
	if (empty($extra_query_vars['tb']))
	{
		// not trackback request, ignore it
		return $bool;
	}

	if (empty($_SERVER['REQUEST_METHOD']))
	{
		// unable to determine request method, stop working
		return $bool;
	}
	if (strtoupper($_SERVER['REQUEST_METHOD']) === 'GET')
	{
		if (isset($_GET['hub_challenge']))
		{
			xfac_subscription_handleIntentVerification($_GET);
			exit();
		}
	}
	elseif (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST')
	{
		// not a POST request, ignore it
		return $bool;
	}

	if (empty($_SERVER['REQUEST_URI']))
	{
		// unable to determine request URI, stop working
		return $bool;
	}
	if (strpos($_SERVER['REQUEST_URI'], 'xfac_callback') === false)
	{
		// request to something else, not our callback, bye bye
		// we don't check $_REQUEST because PHP parser got confused when
		// the POST data is JSON and may work unreliably here
		return $bool;
	}

	$raw = file_get_contents('php://input');
	$json = @json_decode($raw, true);
	if (!is_array($json))
	{
		// unable to parse json, do nothing
		return $bool;
	}

	xfac_subscription_handleCallback($json);
	exit();
}

add_filter('do_parse_request', 'xfac_do_parse_request', 10, 3);
