<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_search_indexPost($config, $postId, $latestSyncDate = 0)
{
    $post = get_post($postId);

    if ($post->post_type !== 'post'
        || $post->post_status !== 'publish'
    ) {
        return false;
    }

    $date = mysql2date('U', $post->post_date_gmt);
    if ($date <= $latestSyncDate) {
        return false;
    }

    $accessToken = xfac_user_getAccessToken($post->post_author);
    if (empty($accessToken)) {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $body = _xfac_syncPost_getPostBody($post, false);
    $link = get_permalink($post);

    return xfac_api_postSearchIndexing($config, $accessToken, 'post', $post->ID,
        $post->post_title, $body, $date, $link);
}

function xfac_search_indexComment($config, $commentId, $latestSyncDate = 0)
{
    $comment = get_comment($commentId);

    if (empty($comment->comment_approved)) {
        return false;
    }

    $date = mysql2date('U', $comment->comment_date_gmt);
    if ($date <= $latestSyncDate) {
        return false;
    }

    $post = get_post($comment->comment_post_ID);
    if (empty($post)) {
        return false;
    }

    $accessToken = '';
    if ($comment->user_id > 0) {
        $accessToken = xfac_user_getAccessToken($comment->user_id);
    }
    if (empty($accessToken)) {
        $accessToken = xfac_user_getSystemAccessToken($config, true);
    }

    $link = get_comment_link($comment);

    return xfac_api_postSearchIndexing($config, $accessToken, 'comment', $comment->comment_ID,
        $post->post_title, $comment->comment_content, $date, $link);
}