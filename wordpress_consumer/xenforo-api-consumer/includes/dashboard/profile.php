<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_user_edit($wpUser)
{
    $config = xfac_option_getConfig();
    if (empty($config)) {
        return;
    }

    $apiRecords = xfac_user_getRecordsByUserId($wpUser->ID);

    if (get_current_user_id() == $wpUser->ID) {
        require(xfac_template_locateTemplate('dashboard_profile.php'));
    } else {
        if (empty($apiRecords)) {
            $xfUsers = array();

            if (xfac_api_hasModuleVersion($config, 'forum', 2015030901)
                && xfac_api_hasModuleVersion($config, 'oauth2', 2015030902)) {
                $userLoginUsers = xfac_api_getUsersFind($config, $wpUser->user_login);
                if (!empty($userLoginUsers['users'])) {
                    foreach ($userLoginUsers['users'] as $userLoginUser) {
                        // compare strlen instead of strtolower to avoid unicode complication
                        if (strlen($userLoginUser['username']) == strlen($wpUser->user_login)) {
                            $xfUsers[$userLoginUser['user_id']] = $userLoginUser;
                        }
                    }
                }

                $adminAccessToken = xfac_user_getAdminAccessToken($config);
                if (!empty($adminAccessToken)) {
                    $emailUsers = xfac_api_getUsersFind($config, '', $wpUser->user_email, $adminAccessToken);
                    if (!empty($emailUsers['users'])) {
                        foreach ($emailUsers['users'] as $emailUser) {
                            $xfUsers[$emailUser['user_id']] = $emailUser;
                        }
                    }
                }
            }
        }

        require(xfac_template_locateTemplate('dashboard_user.php'));
    }
}

add_action('show_user_profile', 'xfac_user_edit');
add_action('edit_user_profile', 'xfac_user_edit');

function xfac_edit_user_profile_update($wpUserId)
{
    $config = xfac_option_getConfig();
    if (empty($config)) {
        return;
    }

    if (!empty($_POST['xfac_disconnect'])) {
        foreach ($_POST['xfac_disconnect'] as $recordId => $confirmed) {
            if ($confirmed) {
                $record = xfac_user_getRecordById($recordId);
                if ($record->user_id == $wpUserId) {
                    xfac_user_deleteRecord($record);
                }
            }
        }
    }

    if (!empty($_POST['xfac_connect'])) {
        $xfUserId = intval($_POST['xfac_connect']);
        if ($xfUserId > 0) {
            $adminAccessToken = xfac_user_getAdminAccessToken($config);

            if (!empty($adminAccessToken)) {
                $userAccessToken = xfac_api_postOauthTokenAdmin($config, $adminAccessToken, $xfUserId);

                if (!empty($userAccessToken)) {
                    $result = xfac_api_getUsersMe($config, $userAccessToken['access_token']);

                    if (!empty($result['user']['user_id'])) {
                        xfac_syncLogin_syncRole($config, get_user_by('id', $wpUserId), $result['user']);
                        if (isset($_POST['role'])) {
                            // because we have already sync'd role, ignore role submitted via POST
                            unset($_POST['role']);
                        }

                        xfac_user_updateRecord($wpUserId, $config['root'], $xfUserId, $result['user'], $userAccessToken);
                    }
                }
            }
        }
    }
}

add_action('edit_user_profile_update', 'xfac_edit_user_profile_update');

function xfac_dashboardProfile_admin_init()
{
    if (!defined('IS_PROFILE_PAGE')) {
        return;
    }

    if (empty($_REQUEST['xfac'])) {
        return;
    }

    switch ($_REQUEST['xfac']) {
        case 'disconnect':
            if (empty($_REQUEST['id'])) {
                return;
            }

            $wpUser = wp_get_current_user();
            if (empty($wpUser)) {
                // huh?!
                return;
            }

            $apiRecords = xfac_user_getRecordsByUserId($wpUser->ID);
            if (empty($apiRecords)) {
                return;
            }

            $requestedRecord = false;
            foreach ($apiRecords as $apiRecord) {
                if ($apiRecord->id == $_REQUEST['id']) {
                    $requestedRecord = $apiRecord;
                }
            }
            if (empty($requestedRecord)) {
                return;
            }

            xfac_user_deleteRecord($requestedRecord);
            wp_redirect('profile.php?xfac=disconnected');
            exit();
            break;
    }
}

add_action('admin_init', 'xfac_dashboardProfile_admin_init');
