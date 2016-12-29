<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_options_init()
{
    if (!empty($_REQUEST['do'])) {
        switch ($_REQUEST['do']) {
            case 'xfac_xf_guest_account':
                require(xfac_template_locateTemplate('dashboard_xfac_xf_guest_account.php'));
                return;
        }
    }

    // prepare common data
    $config = xfac_option_getConfig();
    $meta = array();
    if (!empty($config)) {
        $meta = xfac_option_getMeta($config);
    }

    $currentWpUser = wp_get_current_user();
    $currentWpUserRecords = xfac_user_getRecordsByUserId($currentWpUser->ID);

    $adminAccessToken = xfac_user_getAdminAccessToken($config);

    // setup sections
    $sections = array(array(
        'id' => 'xfac_api',
        'title' => __('API Configuration', 'xenforo-api-consumer'),
    ));
    if (!empty($meta['linkIndex'])) {
        $sections = array_merge($sections, array(
            array(
                'id' => 'xfac_post_comment',
                'title' => __('Post & Comment', 'xenforo-api-consumer'),
            ),
            array(
                'id' => 'xfac_user_role',
                'title' => __('User & Role', 'xenforo-api-consumer'),
            ),
            array(
                'id' => 'xfac_ui',
                'title' => __('Appearances', 'xenforo-api-consumer'),
            ),
        ));
    }
    // always show advanced sections
    $sections[] = array(
        'id' => 'xfac_advanced',
        'title' => __('Advanced', 'xenforo-api-consumer'),
    );

    // setup tabs
    $tab = 'xfac_api';
    if (!empty($_REQUEST['tab'])) {
        $tab = 'xfac_' . $_GET['tab'];
    }
    $sectionFound = false;
    foreach ($sections as $section) {
        if ($section['id'] === $tab) {
            $sectionFound = true;
        }
    }
    if (!$sectionFound) {
        $firstSection = reset($sections);
        $tab = $firstSection['id'];
    }

    // prepare section's data
    switch ($tab) {
        case 'xfac_api':
            $xfGuestRecords = xfac_user_getRecordsByUserId(0);
            $xfAdminRecords = $currentWpUserRecords;
            $configuredAdminRecord = null;

            $xfAdminAccountOption = intval(get_option('xfac_xf_admin_account'));
            if ($xfAdminAccountOption > 0) {
                $configuredAdminRecord = xfac_user_getRecordById($xfAdminAccountOption);
                if (!empty($configuredAdminRecord)) {
                    $found = false;
                    foreach ($xfAdminRecords as $xfAdminRecord) {
                        if ($xfAdminRecord->id == $configuredAdminRecord->id) {
                            $found = true;
                        }
                    }
                    if (!$found) {
                        $xfAdminRecords[] = $configuredAdminRecord;
                    }
                }
            }
            break;
        case 'xfac_post_comment':
            $hourlyNext = wp_next_scheduled('xfac_cron_hourly');

            $tagForumMappings = get_option('xfac_tag_forum_mappings');
            if (!is_array($tagForumMappings)) {
                $tagForumMappings = array();
            }

            $tags = get_terms('post_tag', array('hide_empty' => false));
            break;
        case 'xfac_user_role':
            $syncRoleOption = get_option('xfac_sync_role');
            if (!is_array($syncRoleOption)) {
                $syncRoleOption = array();
            }
            break;
        case 'xfac_ui':
            $optionTopBarForums = get_option('xfac_top_bar_forums');
            if (!is_array($optionTopBarForums)) {
                $optionTopBarForums = array();
            }
            break;
    }

    require(xfac_template_locateTemplate('dashboard_options.php'));
}

function xfac_wpmu_options()
{
    $config = xfac_option_getConfig();
    $meta = xfac_option_getMeta($config);

    require(xfac_template_locateTemplate('dashboard_wpmu_options.php'));
}

add_action('wpmu_options', 'xfac_wpmu_options');

function xfac_update_wpmu_options()
{
    $options = array(
        'xfac_root',
        'xfac_client_id',
        'xfac_client_secret',
    );

    foreach ($options as $optionName) {
        if (!isset($_POST[$optionName])) {
            continue;
        }

        $optionValue = wp_unslash($_POST[$optionName]);
        update_site_option($optionName, $optionValue);
    }
}

add_action('update_wpmu_options', 'xfac_update_wpmu_options');

function xfac_dashboardOptions_admin_init()
{
    if (empty($_REQUEST['page'])) {
        return;
    }
    if ($_REQUEST['page'] !== 'xfac') {
        return;
    }

    if (!empty($_REQUEST['cron'])) {
        switch ($_REQUEST['cron']) {
            case 'hourly':
                do_action('xfac_cron_hourly');
                wp_redirect(admin_url('options-general.php?page=xfac&ran=hourly'));
                exit;
        }
    } elseif (!empty($_REQUEST['do'])) {
        switch ($_REQUEST['do']) {
            case 'xfac_meta':
                update_option('xfac_meta', array());

	            $config = xfac_option_getConfig();
	            xfac_option_getMeta($config, true);

                wp_redirect(admin_url('options-general.php?page=xfac&done=xfac_meta'));
                break;
            case 'xfac_xf_guest_account_submit':
                $config = xfac_option_getConfig();
                if (empty($config)) {
                    wp_die('no_config');
                }

                $username = $_REQUEST['xfac_guest_username'];
                if (empty($username)) {
                    wp_die('no_username');
                }

                $password = $_REQUEST['xfac_guest_password'];
                if (empty($password)) {
                    wp_die('no_password');
                }

                $token = xfac_api_getAccessTokenFromUsernamePassword($config, $username, $password);
                if (empty($token)) {
                    wp_die('no_token');
                }
                $guest = xfac_api_getUsersMe($config, $token['access_token'], false);

                if (empty($guest['user'])) {
                    wp_die('no_xf_user');
                }
                xfac_user_updateRecord(0, $config['root'], $guest['user']['user_id'], $guest['user'], $token);

                $records = xfac_user_getRecordsByUserId(0);
                $record = reset($records);
                update_option('xfac_xf_guest_account', $record->id);

                // force meta rebuild
                update_option('xfac_meta', array());

                wp_redirect(admin_url('options-general.php?page=xfac&done=xfac_xf_guest_account'));
                break;
        }
    }
}

add_action('admin_init', 'xfac_dashboardOptions_admin_init');
