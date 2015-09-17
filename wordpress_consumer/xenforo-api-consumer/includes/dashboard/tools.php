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
    <div class="card">
        <h3 class="title"><?php _e('Auto associate with XenForo', 'xenforo-api-consumer') ?></h3>
        <p><?php _e('Run this tool if you want to go through all WordPress accounts '
            . 'and make sure each of them is associated to a XenForo account. '
            . 'Please note that associating accounts without user consent '
            . 'should not be taken lightly. It\'s recommended to only '
            . 'do this once (right after installing the bridge).'
            , 'xenforo-api-consumer'); ?></p>

        <form action="<?php echo admin_url('tools.php'); ?>">
            <input type="hidden" name="action" value="xfac_tools_connect"/>
            <input type="hidden" name="position" value="0"/>
            <input type="hidden" name="limit" value="10"/>

            <p>
                <label>
                    <input type="checkbox" name="associate" value="1"/>
                    <?php _e('Associate existing account if found', 'xenforo-api-consumer'); ?>
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="push" value="1"/>
                    <?php _e('Create new account if needed', 'xenforo-api-consumer'); ?>
                </label>
            </p>

            <p>
                <input type="submit" value="<?php _e('Start', 'xenforo-api-consumer'); ?>" class="button"/>
            </p>
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
                // similar logic with includes/dashboard/profile.php
                if (strlen($userLoginUser['username']) == strlen($user->user_login)) {
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
        if ($optionValue !== $optionFilters[$optionKey]['default']) {
            $optionsStr .= sprintf('&%s=%s', $optionKey, rawurlencode($optionValue));
        }
    }

    die(sprintf('<script>window.location = "%s";</script>',
        admin_url(sprintf('tools.php?action=xfac_tools_connect%s', $optionsStr))
    ));
}

add_action('admin_action_xfac_tools_connect', 'xfac_tools_connect');

function xfac_tools_search_index()
{
    /** @var wpdb $wpdb */
    global $wpdb;

    $config = xfac_option_getConfig();
    if (empty($config)) {
        wp_die(__('XenForo API configuration is missing.', 'xenforo-api-consumer'));
    }

    if (!xfac_api_hasModuleVersion($config, 'search/indexing', 2015091501)) {
        wp_die(__('Please update XenForo API to run this tool.', 'xenforo-api-consumer'));
    }

    $optionFilters = array(
        'type' => array('filter' => FILTER_DEFAULT, 'default' => ''),
        'position' => array('filter' => FILTER_VALIDATE_INT, 'default' => 0),
        'limit' => array('filter' => FILTER_VALIDATE_INT, 'default' => 10),
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

    $contentTypes = preg_split('#[,\s]#', $options['type'], -1, PREG_SPLIT_NO_EMPTY);
    $contentType = '';
    $contentTable = '';
    $contentIdField = '';
    $syncProviderType = '';
    while (true) {
        if (empty($contentTypes)) {
            die(__('Done.', 'xenforo-api-consumer'));
        }
        $contentType = reset($contentTypes);
        switch ($contentType) {
            case 'post':
                $contentTable = 'posts';
                $contentIdField = 'ID';
                $syncProviderType = 'thread';
                break;
            case 'comment':
                $contentTable = 'comments';
                $contentIdField = 'comment_ID';
                $syncProviderType = 'post';
                break;
        }

        $maxContentId = $wpdb->get_var("SELECT MAX({$contentIdField}) FROM {$wpdb->prefix}{$contentTable}");
        if ($options['position'] < $maxContentId) {
            // position is good, break the while(true) and start working
            break;
        }

        $options['position'] = 0;
        array_shift($contentTypes);
        $options['type'] = implode(',', $contentTypes);
    }

    $contents = $wpdb->get_results($wpdb->prepare("
        SELECT {$contentIdField} AS ID
        FROM {$wpdb->prefix}{$contentTable}
        WHERE {$contentIdField} > %d
        LIMIT %d",
        array(
            $options['position'],
            $options['limit'],
        )
    ));

    $contentIds = array();
    foreach ($contents as $content) {
        $contentIds[] = $content->ID;
    }
    $syncRecords = xfac_sync_getRecordsByProviderTypeAndSyncIds('', $syncProviderType, $contentIds);

    foreach ($contents as $content) {
        $options['position'] = max($options['position'], $content->ID);

        $latestSyncDate = 0;
        foreach ($syncRecords as $syncRecord) {
            if ($syncRecord->sync_id == $content->ID) {
                $latestSyncDate = max($latestSyncDate, $syncRecord->sync_date);
            }
        }

        switch ($contentType) {
            case 'post':
                xfac_search_indexPost($config, $content->ID, $latestSyncDate);
                break;
            case 'comment':
                xfac_search_indexComment($config, $content->ID, $latestSyncDate);
                break;
        }
    }

    $optionsStr = '';
    foreach ($options as $optionKey => $optionValue) {
        if ($optionValue !== $optionFilters[$optionKey]['default']) {
            $optionsStr .= sprintf('&%s=%s', $optionKey, rawurlencode($optionValue));
        }
    }

    die(sprintf('<script>window.location = "%s";</script>',
        admin_url(sprintf('tools.php?action=xfac_tools_search_index%s', $optionsStr))
    ));
}

add_action('admin_action_xfac_tools_search_index', 'xfac_tools_search_index');