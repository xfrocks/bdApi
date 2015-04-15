<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_subscription_handleIntentVerification(array $params)
{
    if (empty($params['client_id'])) {
        // unable to determine hub authorized client
        header('HTTP/1.0 404 Not Found');
        return false;
    }
    $config = xfac_option_getConfig();
    if (empty($config['clientId'])) {
        // no client configured, should not accept subscription
        header('HTTP/1.0 404 Not Found');
        return false;
    }
    if ($config['clientId'] !== $params['client_id']) {
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
    if (empty($config['clientId'])) {
        return;
    }

    $xfThreadIds = array();
    $xfPostIds = array();

    // phrase 1: preparation
    foreach ($json as &$pingRef) {
        if (empty($pingRef['client_id']) OR $pingRef['client_id'] != $config['clientId']) {
            continue;
        }
        if (empty($pingRef['topic'])) {
            continue;
        }
        $parts = explode('_', $pingRef['topic']);
        $pingRef['topic_id'] = array_pop($parts);
        $pingRef['topic_type'] = implode('_', $parts);

        switch ($pingRef['topic_type']) {
            case 'thread_post':
                $xfThreadIds[] = $pingRef['topic_id'];
                $xfPostIds[] = $pingRef['object_data'];
                break;
        }
    }

    // phrase 2: fetch sync records
    $postSyncRecords = array();
    if (!empty($xfPostIds)) {
        $postSyncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', $xfThreadIds);
    }
    $commentSyncRecords = array();
    if (!empty($xfPostIds)) {
        $commentSyncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'post', $xfPostIds);
    }

    // phrase 3: sync data
    foreach ($json as &$pingRef) {
        if (empty($pingRef['topic_type'])) {
            continue;
        }

        switch ($pingRef['topic_type']) {
            case 'thread_post':
                $postSyncRecord = null;
                $commentSyncRecord = null;

                foreach ($postSyncRecords as $_postSyncRecord) {
                    if ($_postSyncRecord->provider_content_id == $pingRef['topic_id']) {
                        $postSyncRecord = $_postSyncRecord;
                    }
                }

                if (!empty($postSyncRecord)) {
                    foreach ($commentSyncRecords as $_commentSyncRecord) {
                        if ($_commentSyncRecord->provider_content_id == $pingRef['object_data']) {
                            $commentSyncRecord = $_commentSyncRecord;
                        }
                    }

                    $pingRef['result'] = _xfac_subscription_handleCallback_threadPost($config, $pingRef, $postSyncRecord, $commentSyncRecord);

                    if (!empty($pingRef['result'])) {
                        xfac_sync_updateRecordDate($postSyncRecord);

                        if (!empty($commentSyncRecord)) {
                            xfac_sync_updateRecordDate($commentSyncRecord);
                        }
                    }
                }
                break;
            case 'user_notification':
                $pingRef['result'] = _xfac_subscription_handleCallback_userNotification($config, $pingRef);
                break;
            case 'user':
                $pingRef['result'] = _xfac_subscription_handleCallback_user($config, $pingRef);
                break;
        }
    }

    // phrase 4: output results
    $results = array();
    foreach ($json as $ping) {
        if (!empty($ping['result'])) {
            $results[] = $ping;
            xfac_log('xfac_subscription_handleCallback %s/%s -> %s', $ping['topic_type'], $ping['topic_id'], $ping['result']);
        }
    }
    echo json_encode($results);
}

function _xfac_subscription_handleCallback_threadPost($config, $ping, $postSyncRecord, $commentSyncRecord)
{
    $wpUserData = xfac_user_getUserDataByApiData($config['root'], $postSyncRecord->syncData['thread']['creator_user_id']);
    $optionSyncPost = intval(get_option('xfac_sync_post_xf_wp')) > 0;
    $optionSyncComment = intval(get_option('xfac_sync_comment_xf_wp')) > 0;
    $accessToken = xfac_user_getAccessToken($wpUserData->ID);

    $xfPost = xfac_api_getPost($config, $ping['object_data'], $accessToken);
    $xfPostIsDeleted = (empty($xfPost['post']) OR !empty($xfPost['post']['post_is_deleted']));

    if (empty($commentSyncRecord)) {
        if (!$xfPostIsDeleted) {
            if (empty($xfPost['post']['post_is_first_post'])) {
                // create a new comment
                if ($optionSyncComment && xfac_syncComment_pullComment($config, $xfPost['post'], $postSyncRecord->sync_id, 'subscription') > 0) {
                    return 'created new comment';
                }
            } else {
                $recordPostUpdateDate = 0;
                $xfPostUpdateDate = 0;
                if (isset($postSyncRecord->syncData['thread']['first_post']['post_update_date'])) {
                    $recordPostUpdateDate = $postSyncRecord->syncData['thread']['first_post']['post_update_date'];
                }
                if (isset($xfPost['post']['post_update_date'])) {
                    $xfPostUpdateDate = $xfPost['post']['post_update_date'];
                }
                if ($recordPostUpdateDate > 0 && $xfPostUpdateDate > 0) {
                    if ($xfPostUpdateDate <= $recordPostUpdateDate) {
                        // the new post is not newer than the post in record
                        // we used XenForo server time (which uses GMT) so it should be correct
                        return false;
                    }
                }

                // update the WordPress post
                $postContent = xfac_api_filterHtmlFromXenForo($xfPost['post']['post_body_html']);

                // remove the link back, if any
                $wfPostLink = get_permalink($postSyncRecord->sync_id);
                $postContent = preg_replace('#<a href="' . preg_quote($wfPostLink, '#') . '"[^>]*>[^<]+</a>$#', '', $postContent);

                $XFAC_SKIP_xfac_save_post_before = !empty($GLOBALS['XFAC_SKIP_xfac_save_post']);
                $GLOBALS['XFAC_SKIP_xfac_save_post'] = true;
                $postUpdated = 0;
                if ($optionSyncPost) {
                    $postUpdated = wp_update_post(array(
                        'ID' => $postSyncRecord->sync_id,
                        'post_content' => $postContent,
                    ));
                }
                $GLOBALS['XFAC_SKIP_xfac_save_post'] = $XFAC_SKIP_xfac_save_post_before;

                if (is_int($postUpdated) AND $postUpdated > 0) {
                    return 'updated post';
                }
            }
        }
    } else {
        if (!$xfPostIsDeleted) {
            // update comment content and approve it automatically
            $commentContent = xfac_api_filterHtmlFromXenForo($xfPost['post']['post_body_html']);

            $XFAC_SKIP_xfac_save_comment_before = !empty($GLOBALS['XFAC_SKIP_xfac_save_comment']);
            $GLOBALS['XFAC_SKIP_xfac_save_comment'] = true;
            $commentUpdated = 0;
            if ($optionSyncComment) {
                $commentUpdated = wp_update_comment(array(
                    'comment_ID' => $commentSyncRecord->sync_id,
                    'comment_content' => $commentContent,
                    'comment_approved' => 1,
                ));
            }
            $GLOBALS['XFAC_SKIP_xfac_save_comment'] = $XFAC_SKIP_xfac_save_comment_before;

            if ($commentUpdated > 0) {
                return 'updated comment';
            }
        } else {
            // check for comment current status and unapprove it
            $wpComment = get_comment($commentSyncRecord->sync_id);

            if (!empty($wpComment->comment_approved)) {
                $XFAC_SKIP_xfac_save_comment_before = !empty($GLOBALS['XFAC_SKIP_xfac_save_comment']);
                $GLOBALS['XFAC_SKIP_xfac_save_comment'] = true;
                $commentUpdated = 0;
                if ($optionSyncComment) {
                    $commentUpdated = wp_update_comment(array(
                        'comment_ID' => $commentSyncRecord->sync_id,
                        'comment_approved' => 0,
                    ));
                }
                $GLOBALS['XFAC_SKIP_xfac_save_comment'] = $XFAC_SKIP_xfac_save_comment_before;

                if ($commentUpdated > 0) {
                    return 'unapproved comment';
                }
            } else {
                return 'comment is unapproved';
            }
        }
    }

    return false;
}

function _xfac_subscription_handleCallback_userNotification($config, $ping)
{
    $accessToken = xfac_user_getSystemAccessToken($config);
    if (empty($accessToken)) {
        return false;
    }

    if (empty($ping['object_data']['notification_id'])) {
        return false;
    }
    $notification = $ping['object_data'];

    if (empty($notification['notification_type'])) {
        return false;
    }
    if (!preg_match('/^post_(?<postId>\d+)_insert$/', $notification['notification_type'], $matches)
        || intval(get_option('xfac_sync_post_xf_wp')) == 0
    ) {
        // currently we only handle post pull here
        return false;
    }
    $postId = $matches['postId'];

    $xfPost = xfac_api_getPost($config, $postId, $accessToken);
    if (empty($xfPost['post']['thread_id'])) {
        return false;
    }

    $postSyncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', array($xfPost['post']['thread_id']));
    if (!empty($postSyncRecords)) {
        return false;
    }

    $xfThread = xfac_api_getThread($config, $xfPost['post']['thread_id'], $accessToken);
    if (empty($xfThread['thread'])) {
        return false;
    }

    $wpTags = xfac_syncPost_getMappedTags($xfThread['thread']['forum_id']);
    if (empty($wpTags)) {
        return false;
    }

    $wpPostId = xfac_syncPost_pullPost($config, $xfThread['thread'], $wpTags, 'subscription');
    if ($wpPostId > 0) {
        return 'created new post';
    }

    return false;
}

function _xfac_subscription_handleCallback_user($config, $ping)
{
    $wpUserData = xfac_user_getUserDataByApiData($config['root'], $ping['object_data']);
    if (empty($wpUserData)) {
        return false;
    }

    $accessToken = xfac_user_getAccessToken($wpUserData->ID);
    if (empty($accessToken)) {
        return false;
    }

    $me = xfac_api_getUsersMe($config, $accessToken, false);
    if (empty($me)) {
        return false;
    }
    $xfUser = $me['user'];

    $wpUser = new WP_User($wpUserData);

    xfac_syncLogin_syncBasic($config, $wpUser, $xfUser);
    xfac_syncLogin_syncRole($config, $wpUser, $xfUser);

    if (xfac_user_updateRecord($wpUserData->ID, $config['root'], $xfUser['user_id'], $xfUser)) {
        return 'updated user record';
    } else {
        return false;
    }
}

function xfac_do_parse_request($bool, $wp, $extra_query_vars)
{
    if (empty($extra_query_vars['tb'])) {
        // not trackback request, ignore it
        return $bool;
    }

    if (empty($_SERVER['REQUEST_METHOD'])) {
        // unable to determine request method, stop working
        return $bool;
    }
    if (strtoupper($_SERVER['REQUEST_METHOD']) === 'GET') {
        if (isset($_GET['hub_challenge'])) {
            xfac_subscription_handleIntentVerification($_GET);
            exit();
        }
    } elseif (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
        // not a POST request, ignore it
        return $bool;
    }

    if (empty($_SERVER['REQUEST_URI'])) {
        // unable to determine request URI, stop working
        return $bool;
    }
    if (strpos($_SERVER['REQUEST_URI'], 'xfac_callback') === false) {
        // request to something else, not our callback, bye bye
        // we don't check $_REQUEST because PHP parser got confused when
        // the POST data is JSON and may work unreliably here
        return $bool;
    }

    $raw = file_get_contents('php://input');
    $json = @json_decode($raw, true);
    if (!is_array($json)) {
        // unable to parse json, do nothing
        return $bool;
    }

    xfac_subscription_handleCallback($json);
    exit();
}

add_filter('do_parse_request', 'xfac_do_parse_request', 10, 3);
