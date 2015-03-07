<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_save_post($postId, WP_Post $post, $update)
{
    if (!empty($GLOBALS['XFAC_SKIP_xfac_save_post'])) {
        return;
    }

    if ($post->post_type != 'post') {
        return;
    }

    if ($post->post_status == 'publish') {
        $tagForumMappings = get_option('xfac_tag_forum_mappings');
        if (empty($tagForumMappings)) {
            return;
        }

        $forumIds = array();

        if (!empty($_POST['xfac_forum_id'])) {
            $forumIds[] = $_POST['xfac_forum_id'];
        }

        foreach ($tagForumMappings as $tagForumMapping) {
            if (!empty($tagForumMapping['term_id'])) {
                if (is_object_in_term($post->ID, 'post_tag', $tagForumMapping['term_id'])) {
                    $forumIds[] = $tagForumMapping['forum_id'];
                }
            }
        }

        if (!empty($forumIds)) {
            $accessToken = xfac_user_getAccessToken($post->post_author);

            if (!empty($accessToken)) {
                $config = xfac_option_getConfig();

                if (!empty($config)) {
                    $existingSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $post->ID);
                    foreach ($existingSyncRecords as $existingSyncRecord) {
                        foreach (array_keys($forumIds) as $key) {
                            if (!empty($existingSyncRecord->syncData['thread']['forum_id']) AND $existingSyncRecord->syncData['thread']['forum_id'] == $forumIds[$key]) {
                                unset($forumIds[$key]);
                                break;
                            }
                        }
                    }

                    $postBody = _xfac_syncPost_getPostBody($post);

                    foreach ($forumIds as $forumId) {
                        $thread = xfac_api_postThread($config, $accessToken, $forumId, $post->post_title, $postBody);

                        if (!empty($thread['thread']['thread_id'])) {
                            $subscribed = array();

                            if (intval(get_option('xfac_sync_comment_xf_wp')) > 0) {
                                $xfPosts = xfac_api_getPostsInThread($config, $thread['thread']['thread_id'], $accessToken);
                                if (empty($xfPosts['subscription_callback']) AND !empty($xfPosts['_headerLinkHub'])) {
                                    if (xfac_api_postSubscription($config, $accessToken, $xfPosts['_headerLinkHub'])) {
                                        $subscribed = array(
                                            'hub' => $xfPosts['_headerLinkHub'],
                                            'time' => time(),
                                        );
                                    }
                                }
                            }

                            xfac_sync_updateRecord('', 'thread', $thread['thread']['thread_id'], $post->ID, 0, array(
                                'forumId' => $forumId,
                                'thread' => $thread['thread'],
                                'direction' => 'push',
                                'subscribed' => $subscribed,
                            ));

                            xfac_log('xfac_save_post pushed to $forum (#%d) as $xfThread (#%d)', $forumId, $thread['thread']['thread_id']);
                        } else {
                            xfac_log('xfac_save_post failed pushing to $forum (#%d)', $forumId);
                        }
                    }

                    foreach ($existingSyncRecords as $existingSyncRecord) {
                        if (!empty($_POST['xfac_delete_sync']) AND in_array($existingSyncRecord->provider_content_id, $_POST['xfac_delete_sync'])) {
                            // user chose to delete this sync record
                            xfac_sync_deleteRecord($existingSyncRecord);

                            if (!empty($existingSyncRecord->syncData['subscribed']['hub'])) {
                                xfac_api_postSubscription($config, $accessToken, $existingSyncRecord->syncData['subscribed']['hub'], 'unsubscribe');
                            }

                            continue;
                        }

                        if (empty($existingSyncRecord->syncData['thread']['first_post']['post_id'])) {
                            // no information about first post to update
                            continue;
                        }

                        $xfPost = xfac_api_putPost($config, $accessToken, $existingSyncRecord->syncData['thread']['first_post']['post_id'], $postBody, array('thread_title' => $post->post_title));

                        if (!empty($xfPost['post_id'])) {
                            $syncData = $existingSyncRecord->syncData;
                            $syncData['direction'] = 'push';
                            $syncData['thread']['first_post'] = $xfPost;

                            xfac_sync_updateRecord('', 'thread', $xfPost['thread_id'], $post->ID, 0, $syncData);
                            xfac_log('xfac_save_post pushed an update for $xfPost (#%d)', $xfPost['post_id']);
                        }
                    }
                }
            }
        }
    }
}

if (intval(get_option('xfac_sync_post_wp_xf')) > 0) {
    add_action('save_post', 'xfac_save_post', 10, 3);
}

function xfac_syncPost_cron()
{
    $config = xfac_option_getConfig();
    if (empty($config)) {
        return;
    }

    $mappedTags = xfac_syncPost_getMappedTags();
    if (empty($mappedTags)) {
        return;
    }

    $forumFollowedSyncRecords = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'forums/followed', 0);
    if (empty($forumFollowedSyncRecords) OR (time() - $forumFollowedSyncRecords[0]->sync_date > 86400)) {
        xfac_update_option_tag_forum_mappings('xfac_tag_forum_mappings', null, get_option('xfac_tag_forum_mappings'));
    }

    $forumIds = array_keys($mappedTags);

    // sync sticky threads first
    $stickyThreads = xfac_api_getThreadsInForums($config, $forumIds, '', 'sticky=1');
    if (!empty($stickyThreads['threads'])) {
        xfac_syncPost_processThreads($config, $stickyThreads['threads'], $mappedTags);
    }

    // now start syncing normal threads
    $threads = xfac_api_getThreadsInForums($config, $forumIds);
    if (!empty($threads['threads'])) {
        xfac_syncPost_processThreads($config, $threads['threads'], $mappedTags);
    }
}

function xfac_update_option_tag_forum_mappings($option, $oldValue, $newValue)
{
    if ($option === 'xfac_tag_forum_mappings') {
        $forumIds = array();
        foreach ($newValue as $tagForumMapping) {
            if (!empty($tagForumMapping['term_id']) AND !empty($tagForumMapping['forum_id'])) {
                $forumIds[] = intval($tagForumMapping['forum_id']);
            }
        }

        $config = xfac_option_getConfig();
        if (!empty($config)) {
            $accessToken = xfac_user_getSystemAccessToken($config);
            if (!empty($accessToken)) {
                xfac_syncPost_followForums($config, $accessToken, $forumIds);
            }
        }
    }
}

function xfac_syncPost_followForums($config, $accessToken, $forumIds)
{
    $forumFollowed = xfac_api_getForumFollowed($config, $accessToken);
    $followedForumIds = array();

    foreach ($forumFollowed['forums'] as $forumFollowedOne) {
        $followedForumIds[] = intval($forumFollowedOne['forum_id']);
    }
    foreach (array_diff($forumIds, $followedForumIds) as $forumId) {
        // follow the forum to get thread notification
        xfac_api_postForumFollower($config, $accessToken, $forumId);
    }
    foreach (array_diff($followedForumIds, $forumIds) as $forumId) {
        // unfollow the forum to save server resources
        xfac_api_deleteForumFollower($config, $accessToken, $forumId);
    }

    // make sure we subscribe for notification callback
    $notifications = xfac_api_getNotifications($config, $accessToken);
    if (empty($notifications['subscription_callback']) AND !empty($notifications['_headerLinkHub'])) {
        xfac_api_postSubscription($config, $accessToken, $notifications['_headerLinkHub']);
    }

    xfac_sync_updateRecord('', 'forums/followed', 0, 0);
}

if (intval(get_option('xfac_sync_post_xf_wp')) > 0) {
    add_action('xfac_cron_hourly', 'xfac_syncPost_cron');
    add_action('update_option', 'xfac_update_option_tag_forum_mappings', 10, 3);
}

function xfac_post_meta_box_info($post)
{
    $config = xfac_option_getConfig();
    $meta = xfac_option_getMeta($config);
    $records = xfac_sync_getRecordsByProviderTypeAndSyncId('', 'thread', $post->ID);

    require(xfac_template_locateTemplate('post_meta_box_info.php'));
}

function xfac_add_meta_boxes($postType, $post)
{
    add_meta_box('xfac_post_info', __('XenForo Info', 'xenforo-api-consumer'), 'xfac_post_meta_box_info', null, 'side');
}

if (intval(get_option('xfac_sync_post_wp_xf')) > 0 OR intval(get_option('xfac_sync_post_xf_wp')) > 0) {
    add_action('add_meta_boxes', 'xfac_add_meta_boxes', 10, 2);
}

function xfac_syncPost_getMappedTags($forumId = 0)
{
    $mappedTags = array();

    $systemTags = get_terms('post_tag', array('hide_empty' => false));
    if (empty($systemTags)) {
        return $mappedTags;
    }

    $tagForumMappings = get_option('xfac_tag_forum_mappings');
    if (empty($tagForumMappings)) {
        return $mappedTags;
    }

    foreach ($tagForumMappings as $tagForumMapping) {
        if (!empty($tagForumMapping['forum_id'])) {
            if (empty($mappedTags[$tagForumMapping['forum_id']])) {
                $mappedTags[$tagForumMapping['forum_id']] = array();
            }

            foreach ($systemTags as $systemTag) {
                if ($systemTag->term_id == $tagForumMapping['term_id']) {
                    $mappedTags[$tagForumMapping['forum_id']][] = $systemTag->name;
                }
            }

        }
    }

    if ($forumId === 0) {
        // get tags for all forums
        return $mappedTags;
    }
    if (isset($mappedTags[$forumId])) {
        return $mappedTags[$forumId];
    } else {
        return array();
    }
}

function xfac_syncPost_processThreads($config, array $threads, array $mappedTags)
{
    $threadIds = array();
    foreach ($threads as $thread) {
        $threadIds[] = $thread['thread_id'];
    }
    $syncRecords = xfac_sync_getRecordsByProviderTypeAndIds('', 'thread', $threadIds);

    foreach ($threads as $thread) {
        $synced = false;

        foreach ($syncRecords as $syncRecord) {
            if ($syncRecord->provider_content_id == $thread['thread_id']) {
                $synced = true;
            }
        }

        if (!$synced) {
            $tagNames = array();
            if (!empty($thread['forum_id']) && isset($mappedTags[$thread['forum_id']])) {
                $tagNames = $mappedTags[$thread['forum_id']];
            }

            if (!empty($tagNames)) {
                xfac_syncPost_pullPost($config, $thread, $tagNames);
            }
        }
    }
}

function xfac_syncPost_pullPost($config, $thread, $tags, $direction = 'pull')
{
    if (empty($thread['creator_user_id'])) {
        return 0;
    }

    $wpUserData = xfac_user_getUserDataByApiData($config['root'], $thread['creator_user_id']);
    if (empty($wpUserData)) {
        return 0;
    }
    $postAuthor = $wpUserData->ID;

    $wpUser = new WP_User($wpUserData);
    $postTypeObj = get_post_type_object('post');
    if (empty($postTypeObj)) {
        // no post type object?!
        return 0;
    }
    if (!$wpUser->has_cap($postTypeObj->cap->create_posts)) {
        // no permission to create posts
        xfac_log('xfac_syncPost_pullPost skipped pulling post because of lack of create_posts capability (user #%d)', $wpUser->ID);
        return 0;
    }

    $postDateGmt = gmdate('Y-m-d H:i:s', $thread['thread_create_date']);
    $postDate = get_date_from_gmt($postDateGmt);

    $postStatus = 'draft';
    if (intval(get_option('xfac_sync_post_xf_wp_publish')) > 0) {
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

    $XFAC_SKIP_xfac_save_post_before = !empty($GLOBALS['XFAC_SKIP_xfac_save_post']);
    $GLOBALS['XFAC_SKIP_xfac_save_post'] = true;
    $wpPostId = wp_insert_post($wpPost);
    $GLOBALS['XFAC_SKIP_xfac_save_post'] = $XFAC_SKIP_xfac_save_post_before;

    if ($wpPostId > 0) {
        $subscribed = array();

        if (intval(get_option('xfac_sync_comment_xf_wp')) > 0) {
            $accessToken = xfac_user_getAccessToken($wpUser->ID);
            if (!empty($accessToken)) {
                $xfPosts = xfac_api_getPostsInThread($config, $thread['thread_id'], $accessToken);
                if (empty($xfPosts['subscription_callback']) AND !empty($xfPosts['_headerLinkHub'])) {
                    if (xfac_api_postSubscription($config, $accessToken, $xfPosts['_headerLinkHub'])) {
                        $subscribed = array(
                            'hub' => $xfPosts['_headerLinkHub'],
                            'time' => time(),
                        );
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
        xfac_log('xfac_syncPost_pullPost pulled $xfThread (#%d) as $wpPost (#%d)', $thread['thread_id'], $wpPostId);
    } else {
        xfac_log('xfac_syncPost_pullPost failed pulling $xfThread (#%d)');
    }

    return $wpPostId;
}

function _xfac_syncPost_getPostBody($post)
{
    if (!!get_option('xfac_sync_post_wp_xf_excerpt')) {
        // this method is implemented with ideas from get_the_excerpt()
        $text = $post->post_excerpt;

        if (empty($text)) {
            $text = $post->post_content;

            $text = strip_shortcodes($text);

            $text = apply_filters('the_content', $text);
            $text = str_replace(']]>', ']]&gt;', $text);

            $excerptLength = apply_filters('excerpt_length', 55);

            $excerptMore = apply_filters('excerpt_more', ' ' . '[&hellip;]');
            $text = wp_trim_words($text, $excerptLength, $excerptMore);
        }

        $text = apply_filters('wp_trim_excerpt', $text, $post->post_excerpt);
    } else {
        $text = $post->post_content;

        $text = apply_filters('the_content', $text);
        $text = str_replace(']]>', ']]&gt;', $text);
    }

    // fix paragraph spacing from WordPress to XenForo
    $text = str_replace('</p>', '</p><br />', $text);

    if (!!get_option('xfac_sync_post_wp_xf_link')) {
        $text .= '<br /><a href="' . get_permalink($post->ID) . '">' . __('Read the whole post here.', 'xenforo-api-consumer') . '</a>';
    }

    return $text;
}
