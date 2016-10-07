<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function xfac_option_getWorkingMode()
{
    static $mode = false;

    if ($mode === false) {
        $mode = 'blog';

        if (is_multisite()) {
            $plugins = get_site_option('active_sitewide_plugins');
            if (isset($plugins['xenforo-api-consumer/xenforo-api-consumer.php'])) {
                // we should have used is_plugin_active_for_network
                // but that is only available in Dashboard...
                $mode = 'network';
            }
        }
    }

    return $mode;
}

function xfac_option_getConfig()
{
    static $config = null;

    if ($config === null) {
        $config = array();

        switch (xfac_option_getWorkingMode()) {
            case 'network':
                $config['root'] = get_site_option('xfac_root');
                $config['clientId'] = get_site_option('xfac_client_id');
                $config['clientSecret'] = get_site_option('xfac_client_secret');
                break;
            case 'blog':
            default:
                $config['root'] = get_option('xfac_root');
                $config['clientId'] = get_option('xfac_client_id');
                $config['clientSecret'] = get_option('xfac_client_secret');
                break;
        }

        if (empty($config['root']) OR empty($config['clientId']) OR empty($config['clientSecret'])) {
            $config = false;
        } else {
            $config['version'] = intval(get_option('xfac_version'));
        }
    }

    return $config;
}

function xfac_option_getMeta($config)
{
    static $rebuiltCount = 0;

    if (empty($config)) {
        return array();
    }

    $meta = get_option('xfac_meta');
    $rebuild = false;

    if (empty($meta) OR empty($meta['linkIndex'])) {
        $rebuild = true;
    } else {
        foreach ($config as $configKey => $configValue) {
            if (empty($meta[$configKey]) OR $meta[$configKey] !== $configValue) {
                $rebuild = true;
                break;
            }
        }
    }

    $xfAdminAccountOption = intval(get_option('xfac_xf_admin_account'));
    $xfAdminAccountMeta = (empty($meta['xfac_xf_admin_account']) ? 0 : intval($meta['xfac_xf_admin_account']));
    if ($xfAdminAccountMeta !== $xfAdminAccountOption) {
        $rebuild = true;
    }

    if ($rebuild AND !empty($_REQUEST['oauth_token'])) {
        // looks like admin enter WordPress url as the root, abort rebuilding
        $rebuild = false;
    }

    if ($rebuild AND $rebuiltCount > 0) {
        // we rebuild once, only retry if $meta is empty
        if (!empty($meta)) {
            $rebuild = false;
        }
    }

    if ($rebuild) {
        xfac_updateNotice('xf_guest_account');
        xfac_updateNotice('xf_admin_account');

        $meta = $config;

        $meta['linkIndex'] = xfac_api_getPublicLink($config, 'index');
        $meta['modules'] = array();
        $meta['forums'] = array();

        if (!empty($meta['linkIndex'])) {
            if ($xfAdminAccountOption) {
                $adminAccessToken = xfac_user_getAdminAccessToken($config);
                if (empty($adminAccessToken)) {
                    // unable to obtain admin access token
                    // probably a missing record or expired refresh token
                    // reset the option
                    update_option('xfac_xf_admin_account', 0);
                    $xfAdminAccountOption = 0;
                }
            }

            $xfGuestAccountOption = intval(get_option('xfac_xf_guest_account'));
            if ($xfGuestAccountOption) {
                $guestAccessToken = xfac_user_getSystemAccessToken($config);
                if (empty($guestAccessToken)) {
                    // unable to obtain guest access token
                    // probably an expired refresh token
                    // reset the option
                    update_option('xfac_xf_guest_account', 0);
                } else {
                    $mappedTags = xfac_syncPost_getMappedTags();
                    if (!empty($mappedTags)) {
                        // make sures the guest account follows required forums
                        // and have the needed notification subscription
                        xfac_syncPost_followForums($config, $guestAccessToken, array_keys($mappedTags));
                    }
                }
            }

            $meta['modules'] = xfac_api_getModules($config);
            $meta['linkAlerts'] = xfac_api_getPublicLink($config, 'account/alerts');
            $meta['linkConversations'] = xfac_api_getPublicLink($config, 'conversations');
            $meta['linkLogin'] = xfac_api_getPublicLink($config, 'login');
            $meta['linkLoginLogin'] = xfac_api_getPublicLink($config, 'login/login');
            $meta['linkRegister'] = xfac_api_getPublicLink($config, 'register');

            $forums = xfac_api_getForums($config);
            if (!empty($forums['forums'])) {
                $meta['forums'] = $forums['forums'];
            }

            $meta['xfac_xf_admin_account'] = $xfAdminAccountOption;
            if (!empty($meta['xfac_xf_admin_account'])) {
                $userGroups = xfac_api_getUserGroups($config, 0, xfac_user_getAdminAccessToken($config));
                if (!empty($userGroups['user_groups'])) {
                    $meta['userGroups'] = $userGroups['user_groups'];
                }

                if (!empty($meta['modules']['subscriptions?hub_topic=user_0'])) {
                    $users = xfac_api_getUsers($config, xfac_api_generateOneTimeToken($config));
                    if (!empty($users['subscription_callback'])) {
                        $meta['user0Subscription'] = true;
                    }
                }
            }
        }

        $rebuiltCount++;
        update_option('xfac_meta', $meta);
        xfac_log('xfac_option_getMeta rebuilt $meta=%s', $meta);
    }

    return $meta;
}
