<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_tool_box()
{
    $config = xfac_option_getConfig();
    if (empty($config)) {
        return;
    }

    $adminAccessToken = xfac_user_getAdminAccessToken($config);
    if (empty($adminAccessToken)) {
        return;
    }

    ?>
    <div class="tool-box">
        <h3 class="title"><?php _e('Create XenForo accounts for WordPress users', 'xenforo-api-consumer') ?></h3>

        <p><?php printf(__('Click <a href="%s">here</a> to start the process.', 'xenforo-api-consumer'), admin_url('tools.php?action=xfac_create_xf_users')); ?></p>
    </div>
<?php
}

add_action('tool_box', 'xfac_tool_box');

function xfac_create_xf_users()
{
    /** @var wpdb $wpdb */
    global $wpdb;

    $config = xfac_option_getConfig();
    if (empty($config)) {
        die(__('XenForo API configuration is missing.', 'xenforo-api-consumer'));
    }

    $adminAccessToken = xfac_user_getAdminAccessToken($config);
    if (empty($adminAccessToken)) {
        die(__('Admin Account\'s access token cannot be obtained.', 'xenforo-api-consumer'));
    }

    $wpUserId = !empty($_REQUEST['position']) ? intval($_REQUEST['position']) : 0;
    $limit = max(1, !empty($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 10);
    $maxWpUserIds = $wpdb->get_var('SELECT MAX(ID) FROM ' . $wpdb->prefix . 'users');

    if ($wpUserId >= $maxWpUserIds) {
        die(__('Done.', 'xenforo-api-consumer'));
    }

    $dbUsers = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'users WHERE ID > ' . $wpUserId . ' LIMIT ' . $limit);
    foreach ($dbUsers as $dbUser) {
        $user = new WP_User($dbUser);
        $wpUserId = max($wpUserId, $user->ID);

        $records = xfac_user_getRecordsByUserId($user->ID);
        if (!empty($records)) {
            // this user has connected
            continue;
        }

        printf(__('Pushing user #%d (%s)', 'xenforo-api-consumer'), $user->ID, $user->user_login);
        echo "<br />\n";

        $result = xfac_api_postUser($config, $user->user_email, $user->user_login, '', array(
            'oauth_token' => $adminAccessToken,
        ));
        if (!empty($result)) {
            $xfUser = $result['user'];
            $token = $result['token'];

            xfac_syncLogin_syncRole($config, $user, $xfUser, false);
            xfac_user_updateRecord($user->ID, $config['root'], $xfUser['user_id'], $xfUser, $token);
            xfac_log('xfac_create_xf_users pushed $wpUser (#%d)', $user->ID);
        } else {
            $errors = xfac_api_getLastErrors();
            if (!is_array($errors)) {
                $errors = array(__('Unknown error', 'xenforo-api-consumer'));
            }
            xfac_log('xfac_create_xf_users failed to push $wpUser (#%d): %s', $user->ID, implode(', ', $errors));
        }
    }

    die(sprintf('<script>window.location = "%s";</script>',
        admin_url(sprintf('tools.php?action=xfac_create_xf_users&position=%d&limit=%d',
            $wpUserId,
            $limit
        ))
    ));
}

add_action('admin_action_xfac_create_xf_users', 'xfac_create_xf_users');