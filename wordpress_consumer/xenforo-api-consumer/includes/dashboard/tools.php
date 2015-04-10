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

    if (!xfac_api_hasModuleVersion($config, 'forum', 2015030901)
        || !xfac_api_hasModuleVersion($config, 'oauth2', 2015030902)
    ) {
        return;
    }

    ?>
    <div class="tool-box">
        <h3 class="title"><?php _e('Auto associate with XenForo', 'xenforo-api-consumer') ?></h3>

        <form action="<?php echo admin_url('tools.php'); ?>">
            <input type="hidden" name="action" value="xfac_tools_connect"/>
            <input type="hidden" name="position" value="0"/>
            <input type="hidden" name="limit" value="10"/>

            <label>
                <input type="checkbox" name="associate" value="1"/>
                <?php _e('Associate XenForo account if found', 'xenforo-api-consumer'); ?>
            </label><br/>

            <label>
                <input type="checkbox" name="push" value="1"/>
                <?php _e('Create XenForo account if needed', 'xenforo-api-consumer'); ?>
            </label><br/>

            <input type="submit" value="<?php _e('Start', 'xenforo-api-consumer'); ?>" class="button"/>
        </form>
    </div>
<?php
}

add_action('tool_box', 'xfac_tool_box');

function xfac_tools_connect()
{
    /** @var wpdb $wpdb */
    global $wpdb;

    $config = xfac_option_getConfig();
    if (empty($config)) {
        wp_die(__('XenForo API configuration is missing.', 'xenforo-api-consumer'));
    }

    $adminAccessToken = xfac_user_getAdminAccessToken($config);
    if (empty($adminAccessToken)) {
        wp_die(__('Admin Account\'s access token cannot be obtained.', 'xenforo-api-consumer'));
    }

    if (!xfac_api_hasModuleVersion($config, 'forum', 2015030901)
        || !xfac_api_hasModuleVersion($config, 'oauth2', 2015030902)
    ) {
        wp_die(__('Please update XenForo API to run this tool.', 'xenforo-api-consumer'));
    }

    $optionFilters = array(
        'position' => array('filter' => FILTER_VALIDATE_INT, 'default' => 0),
        'limit' => array('filter' => FILTER_VALIDATE_INT, 'default' => 10),
        'associate' => array('filter' => FILTER_VALIDATE_INT, 'default' => 0),
        'push' => array('filter' => FILTER_VALIDATE_INT, 'default' => 0),
    );
    $options = array();
    foreach ($optionFilters as $optionKey => $optionFilter) {
        $optionValue = filter_input(INPUT_GET, $optionKey, $optionFilter['filter']);

        if (!empty($optionValue)) {
            $options[$optionKey] = $optionValue;
        } else {
            $options[$optionKey] = $optionFilter['default'];
        }
    }

    if (empty($options['associate']) && empty($options['push'])) {
        wp_die(__('At least one action must be selected: either associate or push', 'xenforo-api-consumer'));
    }

    $maxWpUserIds = $wpdb->get_var('SELECT MAX(ID) FROM ' . $wpdb->prefix . 'users');
    if ($options['position'] >= $maxWpUserIds) {
        die(__('Done.', 'xenforo-api-consumer'));
    }

    $dbUsers = $wpdb->get_results('
        SELECT *
        FROM ' . $wpdb->prefix . 'users
        WHERE ID > ' . $options['position'] . '
        LIMIT ' . $options['limit']
    );

    foreach ($dbUsers as $dbUser) {
        $user = new WP_User($dbUser);
        $options['position'] = max($options['position'], $user->ID);

        $records = xfac_user_getRecordsByUserId($user->ID);
        if (!empty($records)) {
            // this user has connected
            continue;
        }

        printf(__('Processing user #%d (%s)', 'xenforo-api-consumer'), $user->ID, $user->user_login);
        echo "<br />\n";

        $candidates = array();

        $userLoginUsers = xfac_api_getUsersFind($config, $user->user_login);
        if (!empty($userLoginUsers['users'])) {
            foreach ($userLoginUsers['users'] as $userLoginUser) {
                if ($userLoginUser['username'] == $user->user_login) {
                    $candidates[$userLoginUser['user_id']] = $userLoginUser;
                }
            }
        }

        $emailUsers = xfac_api_getUsersFind($config, '', $user->user_email, $adminAccessToken);
        if (!empty($emailUsers['users'])) {
            foreach ($emailUsers['users'] as $emailUser) {
                $candidates[$emailUser['user_id']] = $emailUser;
            }
        }

        if (!empty($candidates) && !empty($options['associate'])) {
            foreach ($candidates as $candidate) {
                $userAccessToken = xfac_api_postOauthTokenAdmin($config, $adminAccessToken, $candidate['user_id']);
                if (!empty($userAccessToken)) {
                    xfac_syncLogin_syncRole($config, $user, $candidate, false);
                    xfac_user_updateRecord($user->ID, $config['root'], $candidate['user_id'], $candidate, $userAccessToken);
                    xfac_log('xfac_tools_connect associated $wpUser (#%d) vs. $xfUser (#%d)',
                        $user->ID,
                        $candidate['user_id']
                    );
                } else {
                    $errors = xfac_api_getLastErrors();
                    if (!is_array($errors)) {
                        $errors = array(__('Unknown error', 'xenforo-api-consumer'));
                    }
                    xfac_log('xfac_tools_connect failed to associate $wpUser (#%d) vs. $xfUser (#%d): %s',
                        $user->ID,
                        $candidate['user_id'],
                        implode(', ', $errors)
                    );
                }
            }
        }

        if (empty($candidates) && !empty($options['push'])) {
            $result = xfac_api_postUser($config, $user->user_email, $user->user_login, '', array(
                'oauth_token' => $adminAccessToken,
            ));
            if (!empty($result)) {
                $xfUser = $result['user'];
                $token = $result['token'];

                xfac_syncLogin_syncRole($config, $user, $xfUser, false);
                xfac_user_updateRecord($user->ID, $config['root'], $xfUser['user_id'], $xfUser, $token);
                xfac_log('xfac_tools_connect pushed $wpUser (#%d)', $user->ID);
            } else {
                $errors = xfac_api_getLastErrors();
                if (!is_array($errors)) {
                    $errors = array(__('Unknown error', 'xenforo-api-consumer'));
                }
                xfac_log('xfac_tools_connect failed to push $wpUser (#%d): %s', $user->ID, implode(', ', $errors));
            }
        }
    }

    $optionsStr = '';
    foreach ($options as $optionKey => $optionValue) {
        if ($optionValue != $optionFilters[$optionKey]['default']) {
            $optionsStr .= sprintf('%s=%s&', $optionKey, rawurlencode($optionValue));
        }
    }

    die(sprintf('<script>window.location = "%s";</script>',
        admin_url(sprintf('tools.php?action=xfac_tools_connect&%s', $optionsStr))
    ));
}

add_action('admin_action_xfac_tools_connect', 'xfac_tools_connect');
