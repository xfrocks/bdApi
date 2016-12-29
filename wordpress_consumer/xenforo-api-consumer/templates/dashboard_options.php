<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

function _xfac_dashboardOptions_renderTagForumMapping($tags, $meta, $i, $tagForumMapping)
{
    $forums = array();
    if (!empty($meta['forums'])) {
        $forums = $meta['forums'];
    }

    // generate fake forum in case we lost connection
    if (!empty($tagForumMapping['forum_id'])) {
        $found = false;

        foreach ($forums as $forum) {
            if ($forum['forum_id'] == $tagForumMapping['forum_id']) {
                $found = true;
            }
        }

        if (!$found) {
            $forums[] = array(
                'forum_id' => $tagForumMapping['forum_id'],
                'forum_title' => '#' . $tagForumMapping['forum_id'],
            );
        }
    }
    ?>

    <div class="<?php echo($tagForumMapping ? 'TagForumMapping_Record' : 'TagForumMapping_Template'); ?>"
         data-i="<?php echo $i; ?>">
        <select name="xfac_tag_forum_mappings[<?php echo $i; ?>][term_id]">
            <option value="0">&nbsp;</option>
            <?php foreach ($tags as $tag): ?>
                <option
                    value="<?php echo esc_attr($tag->term_id); ?>" <?php selected($tag->term_id, !empty($tagForumMapping['term_id']) ? $tagForumMapping['term_id'] : ''); ?>>
                    <?php echo esc_html($tag->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="xfac_tag_forum_mappings[<?php echo $i; ?>][forum_id]">
            <option value="0">&nbsp;</option>
            <?php foreach ($forums as $forum): ?>
                <option
                    value="<?php echo esc_attr($forum['forum_id']); ?>" <?php selected($forum['forum_id'], !empty($tagForumMapping['forum_id']) ? $tagForumMapping['forum_id'] : ''); ?>>
                    <?php echo esc_html($forum['forum_title']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
<?php
}

?>

<div class="wrap">
    <h2 class="nav-tab-wrapper">
        <?php foreach ($sections as $section): ?>
            <a href="<?php echo admin_url('options-general.php?page=xfac&tab=' . substr($section['id'], 5)); ?>"
               class="nav-tab<?php if ($tab == $section['id']) echo ' nav-tab-active'; ?>">
                <?php echo $section['title']; ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <form method="post" action="options.php">
        <?php settings_fields($tab); ?>

        <table class="form-table">

            <?php if ($tab == 'xfac_api'): ?>
                <?php if (xfac_option_getWorkingMode() === 'network'): ?>
                    <tr valign="top">
                        <th scope="row"><label for="xfac_root"><?php _e('API Root', 'xenforo-api-consumer'); ?></label>
                        </th>
                        <td>
                            <input name="xfac_root" type="text" id="xfac_root"
                                   value="<?php echo esc_attr($config['root']); ?>" class="regular-text"
                                   disabled="disabled"/>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr valign="top">
                        <th scope="row"><label for="xfac_root"><?php _e('API Root', 'xenforo-api-consumer'); ?></label>
                        </th>
                        <td>
                            <input name="xfac_root" type="text" id="xfac_root"
                                   value="<?php echo esc_attr($config['root']); ?>" class="regular-text"/>

                            <p class="description">
                                <?php echo xfac_api_getVersionSuggestionText($config, $meta); ?>
                                <a href="<?php echo admin_url('options-general.php?page=xfac&do=xfac_meta'); ?>"><?php _e('Reload API Info.', 'xenforo-api-consumer'); ?></a>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label
                                for="xfac_client_id"><?php _e('API Key', 'xenforo-api-consumer'); ?></label></th>
                        <td>
                            <input name="xfac_client_id" type="text" id="xfac_client_id"
                                   value="<?php echo esc_attr($config['clientId']); ?>" class="regular-text"/>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label
                                for="xfac_client_secret"><?php _e('API Secret', 'xenforo-api-consumer'); ?></label></th>
                        <td>
                            <input name="xfac_client_secret" type="text" id="xfac_client_secret"
                                   value="<?php echo esc_attr($config['clientSecret']); ?>" class="regular-text"/>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr valign="top">
                    <th scope="row">
                        <?php _e('XenForo Guest Account', 'xenforo-api-consumer'); ?>
                    </th>
                    <td>
                        <?php if (!empty($xfGuestRecords)): ?>
                            <?php foreach ($xfGuestRecords as $xfGuestRecord): ?>
                                <fieldset>
                                    <label for="xfac_xf_guest_account_<?php echo $xfGuestRecord->id; ?>">
                                        <input name="xfac_xf_guest_account" type="radio"
                                               id="xfac_xf_guest_account_<?php echo $xfGuestRecord->id; ?>"
                                               value="<?php echo $xfGuestRecord->id; ?>" <?php checked($xfGuestRecord->id, get_option('xfac_xf_guest_account')); ?> />
                                        <?php echo $xfGuestRecord->profile['username']; ?>
                                    </label>
                                </fieldset>
                            <?php endforeach; ?>
                            <fieldset><a
                                    href="<?php echo admin_url('options-general.php?page=xfac&do=xfac_xf_guest_account'); ?>"><?php _e('Change account', 'xenforo-api-consumer'); ?></a>
                            </fieldset>
                        <?php else: ?>
                            <fieldset><a
                                    href="<?php echo admin_url('options-general.php?page=xfac&do=xfac_xf_guest_account'); ?>"><?php _e('Add account', 'xenforo-api-consumer'); ?></a>
                            </fieldset>
                        <?php endif; ?>

                        <fieldset>
                            <label for="xfac_xf_guest_account_0">
                                <input name="xfac_xf_guest_account" type="radio" id="xfac_xf_guest_account_0"
                                       value="0" <?php checked(0, intval(get_option('xfac_xf_guest_account'))); ?> />
                                <?php _e('Disabled', 'xenforo-api-consumer'); ?>
                            </label>
                        </fieldset>

                        <p class="description"><?php _e('The guest account will be used when contents need to be sync\'d to XenForo but no connected account can be found.', 'xenforo-api-consumer'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <?php _e('XenForo Admin Account', 'xenforo-api-consumer'); ?>
                    </th>
                    <td>
                        <?php if (!empty($xfAdminRecords)): ?>
                            <?php foreach ($xfAdminRecords as $xfAdminRecord): ?>
                                <fieldset>
                                    <label for="xfac_xf_admin_account_<?php echo $xfAdminRecord->id; ?>">
                                        <input name="xfac_xf_admin_account" type="radio"
                                               id="xfac_xf_admin_account_<?php echo $xfAdminRecord->id; ?>"
                                               value="<?php echo $xfAdminRecord->id; ?>" <?php checked($xfAdminRecord->id, get_option('xfac_xf_admin_account')); ?> />
                                        <?php echo $xfAdminRecord->profile['username']; ?>
                                        <?php if (wp_get_current_user()->ID == $xfAdminRecord->user_id): ?>
                                            (<?php _e('Your associated account', 'xenforo-api-consumer'); ?>)
                                        <?php endif; ?>
                                    </label>
                                </fieldset>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <fieldset>
                            <label for="xfac_xf_admin_account_0">
                                <input name="xfac_xf_admin_account" type="radio" id="xfac_xf_admin_account_0"
                                       value="0" <?php checked(0, intval(get_option('xfac_xf_admin_account'))); ?> />
                                <?php _e('Disabled', 'xenforo-api-consumer'); ?>
                            </label>
                        </fieldset>

                        <p class="description">
                            <?php _e('The admin account is used for administration task such as user group sync. An Administrator WordPress account must associate with an Administrative XenForo account to setup this.', 'xenforo-api-consumer'); ?>
                            <?php if (!empty($configuredAdminRecord) AND empty($meta['userGroups']) AND wp_get_current_user()->ID == $configuredAdminRecord->user_id): ?>
                                <?php if (!xfac_user_recordHasAdmincpScope($configuredAdminRecord)): ?>
                                    <?php _e('Looks like your associated account doesn\'t have <span style="font-family: Courier New">admincp</span> API scope.', 'xenforo-api-consumer'); ?>
                                    <a href="<?php echo site_url('wp-login.php?xfac=authorize&admin=1&redirect_to=' . rawurlencode(admin_url('profile.php')), 'login_post'); ?>"><?php _e('Click here to attempt to fix it.', 'xenforo-api-consumer'); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            <?php endif; ?>

            <?php if (!empty($meta['linkIndex'])): ?>
                <?php if ($tab == 'xfac_post_comment'): ?>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Post Sync', 'xenforo-api-consumer'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="xfac_sync_post_wp_xf">
                                    <input name="xfac_sync_post_wp_xf" type="checkbox" id="xfac_sync_post_wp_xf"
                                           value="1" <?php checked('1', get_option('xfac_sync_post_wp_xf')); ?> />
                                    <?php _e('Post from WordPress to XenForo', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php echo sprintf(
                                        __('Sync WordPress post to XenForo as thread. '
                                            . 'Only posts with pre-configured tags will be sync\'d, see %s option below.',
                                            'xenforo-api-consumer'),
                                        __('Tag / Forum Mappings', 'xenforo-api-consumer')
                                    ); ?></p>

                                <div style="margin-left: 20px;">
                                    <label for="xfac_sync_post_wp_xf_excerpt">
                                        <input name="xfac_sync_post_wp_xf_excerpt" type="checkbox"
                                               id="xfac_sync_post_wp_xf_excerpt"
                                               value="1" <?php checked('1', get_option('xfac_sync_post_wp_xf_excerpt')); ?> />
                                        <?php _e('Sync excerpt only', 'xenforo-api-consumer'); ?>
                                    </label>

                                    <p class="description"><?php _e('Only use excerpt (instead of the whole post) to create XenForo thread.', 'xenforo-api-consumer'); ?></p>

                                    <label for="xfac_sync_post_wp_xf_link">
                                        <input name="xfac_sync_post_wp_xf_link" type="checkbox"
                                               id="xfac_sync_post_wp_xf_link"
                                               value="1" <?php checked('1', get_option('xfac_sync_post_wp_xf_link')); ?> />
                                        <?php _e('Include post link', 'xenforo-api-consumer'); ?>
                                    </label>

                                    <p class="description"><?php _e('Insert a backlink to WordPress post at the end of XenForo thread.', 'xenforo-api-consumer'); ?></p>
                                </div>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_sync_post_xf_wp">
                                    <input name="xfac_sync_post_xf_wp" type="checkbox" id="xfac_sync_post_xf_wp"
                                           value="1" <?php checked('1', get_option('xfac_sync_post_xf_wp')); ?> />
                                    <?php _e('Thread from XenForo to WordPress', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php echo sprintf(
                                        __('Sync XenForo thread to WordPress as draft. '
                                            . 'Only threads in pre-configured forums will be sync\'d, see %s option below.',
                                            'xenforo-api-consumer'),
                                        __('Tag / Forum Mappings', 'xenforo-api-consumer')
                                    ); ?></p>

                                <div style="margin-left: 20px;">
                                    <label for="xfac_sync_post_xf_wp_publish">
                                        <input name="xfac_sync_post_xf_wp_publish" type="checkbox"
                                               id="xfac_sync_post_xf_wp_publish"
                                               value="1" <?php checked('1', get_option('xfac_sync_post_xf_wp_publish')); ?> />
                                        <?php _e('Auto-publish synchronized post', 'xenforo-api-consumer'); ?>
                                    </label>

                                    <p class="description"><?php _e('Publish sync\'d WordPress post immediately.', 'xenforo-api-consumer'); ?></p>
                                </div>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_search_index_post">
                                    <input name="xfac_search_index_post" type="checkbox" id="xfac_search_index_post"
                                           value="1" <?php checked('1', get_option('xfac_search_index_post')); ?> />
                                    <?php _e('Search Indexing', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description">
                                    <?php _e('Index WordPress post in XenForo search engine, '
                                        . 'making it possible to show blog entries when doing a regular search within the forums.',
                                        'xenforo-api-consumer'); ?>

                                    <?php if (!isset($meta['modules']['search/indexing']) || $meta['modules']['search/indexing'] < 2015091501): ?>
                                        <span style="color: red">
                                            <?php echo sprintf(
                                                __('This feature requires %s-%s, it will not work until API server is updated.', 'xenforo-api-consumer'),
                                                'search/indexing',
                                                2015091501
                                            ); ?>
                                            <?php _e('You may need to edit the client in XenForo AdminCP'
                                                . ' and enable the "Can index search contents" option in order to use search/indexing.',
                                                'xenforo-api-consumer'); ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="<?php echo admin_url('tools.php?action=xfac_tools_search_index&type=post'); ?>">
                                            <?php _e('Rebuild search index', 'xenforo-api-consumer'); ?></a>.
                                    <?php endif; ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Comment Sync', 'xenforo-api-consumer'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="xfac_sync_comment_wp_xf">
                                    <input name="xfac_sync_comment_wp_xf" type="checkbox" id="xfac_sync_comment_wp_xf"
                                           value="1" <?php checked('1', get_option('xfac_sync_comment_wp_xf')); ?> />
                                    <?php _e('Comment from WordPress to XenForo', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Sync comment in WordPress post to connected XenForo thread as reply.', 'xenforo-api-consumer'); ?></p>

                                <div style="margin-left: 20px;">
                                    <label for="xfac_sync_comment_wp_xf_as_guest">
                                        <input name="xfac_sync_comment_wp_xf_as_guest" type="checkbox"
                                               id="xfac_sync_comment_wp_xf_as_guest"
                                               value="1" <?php checked('1', get_option('xfac_sync_comment_wp_xf_as_guest')); ?> />
                                        <?php _e('Sync as guest if account is not connected', 'xenforo-api-consumer'); ?>
                                    </label>

                                    <p class="description"><?php echo sprintf(
                                            __('If no XenForo account can be found for post creator, '
                                                . 'create the reply using account configured in %s option below.',
                                                'xenforo-api-consumer'),
                                            __('XenForo Guest Account', 'xenforo-api-consumer')
                                        ); ?></p>
                                </div>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_sync_comment_xf_wp">
                                    <input name="xfac_sync_comment_xf_wp" type="checkbox" id="xfac_sync_comment_xf_wp"
                                           value="1" <?php checked('1', get_option('xfac_sync_comment_xf_wp')); ?> />
                                    <?php _e('Reply from XenForo to WordPress', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Sync reply in XenForo thread to connected WordPress post as comment.', 'xenforo-api-consumer'); ?></p>

                                <div style="margin-left: 20px;">
                                    <label for="xfac_sync_comment_xf_wp_as_guest">
                                        <input name="xfac_sync_comment_xf_wp_as_guest" type="checkbox"
                                               id="xfac_sync_comment_xf_wp_as_guest"
                                               value="1" <?php checked('1', get_option('xfac_sync_comment_xf_wp_as_guest')); ?> />
                                        <?php _e('Sync as guest if account is not connected', 'xenforo-api-consumer'); ?>
                                    </label>

                                    <p class="description"><?php _e('If no WordPress account can be found for reply poster, '
                                            . 'create the comment as a guest comment.', 'xenforo-api-consumer'); ?></p>
                                </div>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_search_index_comment">
                                    <input name="xfac_search_index_comment" type="checkbox" id="xfac_search_index_comment"
                                           value="1" <?php checked('1', get_option('xfac_search_index_comment')); ?> />
                                    <?php _e('Search Indexing', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description">
                                    <?php _e('Index WordPress comment in XenForo search engine, '
                                        . 'making it possible to show comments when doing a regular search within the forums.',
                                        'xenforo-api-consumer'); ?>

                                    <?php if (!isset($meta['modules']['search/indexing']) || $meta['modules']['search/indexing'] < 2015091501): ?>
                                        <span style="color: red">
                                            <?php echo sprintf(
                                                __('This feature requires %s-%s, it will not work until API server is updated.', 'xenforo-api-consumer'),
                                                'search/indexing',
                                                2015091501
                                            ); ?>
                                            <?php _e('You may need to edit the client in XenForo AdminCP'
                                                . ' and enable the "Can index search contents" option in order to use search/indexing.',
                                                'xenforo-api-consumer'); ?>
                                        </span>
                                    <?php else: ?>
                                        <a href="<?php echo admin_url('tools.php?action=xfac_tools_search_index&type=comment'); ?>">
                                            <?php _e('Rebuild search index', 'xenforo-api-consumer'); ?></a>.
                                    <?php endif; ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Synchronization', 'xenforo-api-consumer'); ?><br/>
                        </th>
                        <td>
                            <?php _e('Next Run', 'xenforo-api-consumer'); ?>:
                            <?php echo date_i18n('H:i', $hourlyNext + get_option('gmt_offset') * HOUR_IN_SECONDS); ?>
                            (<a href="options-general.php?page=xfac&cron=hourly"><?php _e('Sync Now', 'xenforo-api-consumer'); ?></a>)
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><label
                                for="xfac_tag_forum_mappings"><?php _e('Tag / Forum Mappings', 'xenforo-api-consumer'); ?></label>
                        </th>
                        <td>
                            <?php

                            $i = -1;
                            foreach (array_values($tagForumMappings) as $i => $tagForumMapping) {
                                if (empty($tagForumMapping['term_id']) OR empty($tagForumMapping['forum_id'])) {
                                    continue;
                                }

                                _xfac_dashboardOptions_renderTagForumMapping($tags, $meta, $i, $tagForumMapping);
                            }

                            if (empty($tags)) {
                                _e('No WordPress tags found', 'xenforo-api-consumer');
                            } elseif (empty($meta['forums'])) {
                                _e('No XenForo forums found', 'xenforo-api-consumer');
                            } else {
                                _xfac_dashboardOptions_renderTagForumMapping($tags, $meta, ++$i, null);
                            }
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if ($tab == 'xfac_user_role'): ?>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('User Sync', 'xenforo-api-consumer'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="xfac_sync_avatar_xf_wp">
                                    <input name="xfac_sync_avatar_xf_wp" type="checkbox" id="xfac_sync_avatar_xf_wp"
                                           value="1" <?php checked('1', get_option('xfac_sync_avatar_xf_wp')); ?> />
                                    <?php _e('Avatar from XenForo to WordPress', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Use avatar URL provided by XenForo as WordPress user avatar.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>

                            <?php if (!empty($adminAccessToken)): ?>
                                <fieldset>
                                    <label for="xfac_sync_user_wp_xf_password">
                                        <input name="xfac_sync_user_wp_xf_password" type="checkbox"
                                               id="xfac_sync_user_wp_xf_password"
                                               value="1" <?php checked('1', get_option('xfac_sync_user_wp_xf_password')); ?> />
                                        <?php _e('Password from WordPress to XenForo', 'xenforo-api-consumer'); ?>
                                    </label>

                                    <p class="description">
                                        <?php echo sprintf(__('Use access token of the configured Admin Account to to update XenForo password when user WordPress password is changed. '
                                            . 'Please note that due to technical limitation, XenForo password change cannot be detected from WordPress. A workaround for this is to enable "%s".',
                                            'xenforo-api-consumer'), __('Accept XenForo login credentials', 'xenforo-api-consumer')); ?>

                                        <?php if (!isset($meta['modules']['forum']) || $meta['modules']['forum'] < 2014112801): ?>
                                            <span style="color: red"><?php echo sprintf(
                                                    __('This feature requires %s-%s, it will not work until API server is updated.', 'xenforo-api-consumer'),
                                                    'forum',
                                                    2014112801
                                                ); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </fieldset>
                            <?php endif; ?>

                            <fieldset>
                                <label for="xfac_sync_user_wp_xf">
                                    <input name="xfac_sync_user_wp_xf" type="checkbox" id="xfac_sync_user_wp_xf"
                                           value="1" <?php checked('1', get_option('xfac_sync_user_wp_xf')); ?> />
                                    <?php _e('Create XenForo account for WordPress user', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description">
                                    <?php _e('Try to create XenForo account for WordPress user if he/she has\'t have a XenForo account yet. '
                                        . 'This is done everytime user logs into WordPress using a WordPress credential.', 'xenforo-api-consumer'); ?>

                                    <?php if (!isset($meta['modules']['oauth2']) || $meta['modules']['oauth2'] < 2014030701): ?>
                                        <span style="color: red"><?php echo sprintf(
                                                __('This feature requires %s-%s, it will not work until API server is updated.', 'xenforo-api-consumer'),
                                                'oauth2',
                                                2014030701
                                            ); ?></span>
                                    <?php endif; ?>
                                </p>

                                <?php if (!empty($adminAccessToken)): ?>
                                    <div style="margin-left: 20px;">
                                        <label for="xfac_sync_user_wp_xf_as_admin">
                                            <input name="xfac_sync_user_wp_xf_as_admin" type="checkbox"
                                                   id="xfac_sync_user_wp_xf_as_admin"
                                                   value="1" <?php checked('1', get_option('xfac_sync_user_wp_xf_as_admin')); ?> />
                                            <?php _e('Create new account with XenForo Admin Account', 'xenforo-api-consumer'); ?>
                                        </label>

                                        <p class="description"><?php _e('Use access token of the configured Admin Account to create new accounts, '
                                                . 'the new accounts\' state will be "valid", bypassing email confirmation even if XenForo was setup to require that.', 'xenforo-api-consumer'); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-left: 20px;">
                                    <label for="xfac_sync_user_wp_xf_on_register">
                                        <input name="xfac_sync_user_wp_xf_on_register" type="checkbox"
                                               id="xfac_sync_user_wp_xf_on_register"
                                               value="1" <?php checked('1', get_option('xfac_sync_user_wp_xf_on_register')); ?> />
                                        <?php _e('Create new account on WordPress registration', 'xenforo-api-consumer'); ?>
                                    </label>

                                    <p class="description"><?php _e('Create XenForo account immediately after a successful WordPress signup '
                                            . 'instead of waiting for the first login. Doing this may improve UX but user will have to '
                                            . 'request a new XenForo password if he/she ever wants to disconnect between WordPress and XenForo', 'xenforo-api-consumer'); ?></p>
                                </div>
                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Login Sync', 'xenforo-api-consumer'); ?><br/>
                        </th>
                        <td>
                            <fieldset>
                                <label for="xfac_bypass_users_can_register">
                                    <input name="xfac_bypass_users_can_register" type="checkbox"
                                           id="xfac_bypass_users_can_register"
                                           value="1" <?php checked('1', get_option('xfac_bypass_users_can_register')); ?> />
                                    <?php _e('Always create new WordPress account', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Bypass WordPress option "Anyone can register" when creating WordPress account for XenForo user.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_sync_password">
                                    <input name="xfac_sync_password" type="checkbox" id="xfac_sync_password"
                                           value="1" <?php checked('1', get_option('xfac_sync_password')); ?> />
                                    <?php _e('Accept XenForo login credentials', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Allow user to enter XenForo username and password into WordPress login form.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_sync_login">
                                    <input name="xfac_sync_login" type="checkbox" id="xfac_sync_login"
                                           value="1" <?php checked('1', get_option('xfac_sync_login')); ?> />
                                    <?php _e('Sync logged-in cookie', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Use JavaScript to detect XenForo logged-in status and let user login to WordPress automatically. '
                                        . 'If user logs into WordPress first, try to register XenForo logged-in status.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>
                        </td>
                    </tr>

                    <?php if (!empty($meta['userGroups'])): ?>
                        <tr valign="top">
                            <th scope="row">
                                <?php _e('Role / Group Mapping', 'xenforo-api-consumer'); ?><br/>
                            </th>
                            <td>
                                <table cellspacing="0" cellpadding="0" style="border-spacing: 0">
                                    <?php foreach (get_editable_roles() as $roleName => $roleInfo): ?>
                                        <?php $syncRoleOptionThisRole = (!empty($syncRoleOption[$roleName]) ? $syncRoleOption[$roleName] : 0); ?>
                                        <tr>
                                            <td style="margin: 0; padding: 0 10px">
                                                <label for="xfac_sync_role_<?php echo $roleName; ?>">
                                                    <?php echo $roleInfo['name']; ?>
                                                </label>
                                            </td>
                                            <td style="margin: 0; padding: 10px 0">
                                                <select id="xfac_sync_role_<?php echo $roleName; ?>"
                                                        name="xfac_sync_role[<?php echo $roleName; ?>]">
                                                    <option
                                                        value="0" <?php selected(0, $syncRoleOptionThisRole); ?>></option>
                                                    <option
                                                        value="-1" <?php selected(-1, $syncRoleOptionThisRole); ?>><?php _e('Do not sync', 'xenforo-api-consumer'); ?></option>
                                                    <?php foreach ($meta['userGroups'] as $userGroup): ?>
                                                        <option
                                                            value="<?php echo $userGroup['user_group_id']; ?>" <?php selected($userGroup['user_group_id'], $syncRoleOptionThisRole); ?>>
                                                            <?php echo $userGroup['user_group_title']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                                <p class="description"><?php _e('Users who come from XenForo will have their WordPress roles setup as configured in this table. ' .
                                        'The roles are checked from the most powerful role down so user will have one role that fits best to their user groups. ' .
                                        'Choose "Do not sync" for roles that need to be ignored by the sync logic (WordPress accounts with these roles will be protected from changing).', 'xenforo-api-consumer'); ?></p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">&nbsp;</th>
                            <td>
                                <label for="xfac_sync_role_wp_xf">
                                    <input name="xfac_sync_role_wp_xf" type="checkbox" id="xfac_sync_role_wp_xf"
                                           value="1" <?php checked('1', get_option('xfac_sync_role_wp_xf')); ?> />
                                    <?php _e('Role from WordPress to XenForo', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Update XenForo user groups when WordPress roles are changed.', 'xenforo-api-consumer'); ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr valign="top">
                            <th scope="row">
                                <?php _e('Role / Group Mapping', 'xenforo-api-consumer'); ?><br/>
                            </th>
                            <td>
                                <p class="description">
                                    <?php $optionsUrl = admin_url('options-general.php?page=xfac'); ?>
                                    <?php $loginUrl = site_url('wp-login.php?xfac=authorize&admin=1&redirect_to=' . rawurlencode($optionsUrl), 'login_post'); ?>
                                    <?php if (!empty($adminAccessToken)): ?>
                                        <?php _e('Could not get user groups information from API server.'); ?>
                                        <?php if (!empty($currentWpUserRecords)): ?>
                                            <?php if (xfac_user_recordsHaveAdmincpScope($currentWpUserRecords)): ?>
                                                <a href="<?php echo admin_url('options-general.php?page=xfac&do=xfac_meta'); ?>"><?php _e('Reload API Info.', 'xenforo-api-consumer'); ?></a>
                                            <?php else: ?>
                                                <a href="<?php echo $loginUrl; ?>"><?php _e('Click here to refresh your association.', 'xenforo-api-consumer'); ?></a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="<?php echo $loginUrl; ?>"><?php _e('Click here to associate your account.', 'xenforo-api-consumer'); ?></a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php _e('You need to associate an Administrative XenForo account and select it as Admin Account.', 'xenforo-api-consumer'); ?>
                                        <?php if (xfac_user_getRecordsByUserId(get_current_user_id())): ?>
                                            <a href="<?php echo $optionsUrl; ?>"><?php _e('Click here to configure Admin Account.', 'xenforo-api-consumer'); ?></a>
                                        <?php else: ?>
                                            <a href="<?php echo $loginUrl; ?>"><?php _e('Click here to associate your XenForo account.', 'xenforo-api-consumer'); ?></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <?php foreach ($syncRoleOption as $roleName => $userGroupId): ?>
                            <input type="hidden" name="xfac_sync_role[<?php echo $roleName; ?>]"
                                   value="<?php echo $userGroupId; ?>"/>
                        <?php endforeach; ?>
                        <input type="hidden" name="xfac_sync_role_wp_xf"
                               value="<?php echo get_option('xfac_sync_role_wp_xf'); ?>"/>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($tab == 'xfac_ui'): ?>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Top Bar', 'xenforo-api-consumer'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="xfac_top_bar_forums_0">
                                    <input name="xfac_top_bar_forums[]" type="checkbox" id="xfac_top_bar_forums_0"
                                           value="0" <?php checked(true, in_array(0, $optionTopBarForums)); ?> />
                                    <?php _e('Forums link', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Show XenForo index link in Top Bar. XenForo forums can be chosen to show as submenu items below.', 'xenforo-api-consumer'); ?></p>

                                <?php if (!empty($meta['forums'])): ?>
                                    <div style="margin-left: 20px;">
                                        <?php foreach ($meta['forums'] as $forum): ?>
                                            <label for="xfac_top_bar_forums_<?php echo $forum['forum_id']; ?>">
                                                <input name="xfac_top_bar_forums[]" type="checkbox"
                                                       id="xfac_top_bar_forums_<?php echo $forum['forum_id']; ?>"
                                                       value="<?php echo $forum['forum_id']; ?>" <?php checked(true, in_array($forum['forum_id'], $optionTopBarForums)); ?> />
                                                <?php echo $forum['forum_title']; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_top_bar_notifications">
                                    <input name="xfac_top_bar_notifications" type="checkbox"
                                           id="xfac_top_bar_notifications"
                                           value="1" <?php checked('1', get_option('xfac_top_bar_notifications')); ?> />
                                    <?php _e('Show Alerts link', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Show XenForo alerts link in Top Bar. The number of unread alerts will be updated via AJAX.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>
                            <fieldset>
                                <label for="xfac_top_bar_conversations">
                                    <input name="xfac_top_bar_conversations" type="checkbox"
                                           id="xfac_top_bar_conversations"
                                           value="1" <?php checked('1', get_option('xfac_top_bar_conversations')); ?> />
                                    <?php _e('Show Conversations link', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Show XenForo conversations link in Top Bar. The number of new conversations will be updated via AJAX.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>

                            <fieldset>
                                <label for="xfac_top_bar_replace">
                                    <input name="xfac_top_bar_replace" type="checkbox" id="xfac_top_bar_replace"
                                           value="1" <?php checked('1', get_option('xfac_top_bar_replace')); ?> />
                                    <?php _e('Replace Admin Bar', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Replace WordPress Admin Bar with Top Bar completely. All WordPress links will be removed.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>
                            <fieldset>
                                <label for="xfac_top_bar_always">
                                    <input name="xfac_top_bar_always" type="checkbox" id="xfac_top_bar_always"
                                           value="1" <?php checked('1', get_option('xfac_top_bar_always')); ?> />
                                    <?php _e('Always Show', 'xenforo-api-consumer'); ?>
                                </label>

                                <p class="description"><?php _e('Let the Top Bar show up every time. A login form will appear for guests.', 'xenforo-api-consumer'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>

            <!-- always show advanced sections -->
            <?php if ($tab == 'xfac_advanced'): ?>
                <p style="color: red; font-weight: bold;"><?php _e('Below are advanced setting configurations. Proceed with caution. '
                        . 'Some of them are meant for debugging purpose only and may affect performance.', 'xenforo-api-consumer'); ?></p>

                <tr valign="top">
                    <th scope="row"><label for="xfac_server_ip"><?php _e('XenForo Server IP Address', 'xenforo-api-consumer'); ?></label>
                    </th>
                    <td>
                        <input name="xfac_server_ip" type="text" id="xfac_server_ip"
                               value="<?php echo esc_attr(get_option('xfac_server_ip')); ?>" class="regular-text"/>

                        <p class="description"><?php _e('A firewall prevents your WordPress server from reaching your XenForo using the public IP address? '
                                . 'Running the sites on the same server behind a CDN and it needs better performance? '
                                . 'Enter the private / internal XenForo server IP address here to connect directly to it.', 'xenforo-api-consumer'); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">&nbsp;</label>
                    </th>
                    <td>
                        <label for="xfac_curl_verify_off">
                            <input name="xfac_curl_verify_off" type="checkbox" id="xfac_curl_verify_off"
                                   value="1" <?php checked('1', get_option('xfac_curl_verify_off')); ?> />
                            <?php _e('Disable libcurl Verifications', 'xenforo-api-consumer'); ?>
                        </label>

                        <p class="description"><?php echo sprintf(__('It\'s not recommended to enable this option '
                                . 'unless the option "%s" has been set to a secure internal ip. '
                                . 'The affected options are:<ul style="font-family: \'Courier New\'"><li>'
                                . '<a href="http://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html" '
                                . 'target="_blank" tabindex="-1">CURLOPT_SSL_VERIFYPEER</a> = false</li>'
                                . '<li><a href="http://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYHOST.html" '
                                . 'target="_blank" tabindex="-1">CURLOPT_SSL_VERIFYHOST</a> = 0</li></ul>',
                                'xenforo-api-consumer'),
                                __('XenForo Server IP Address', 'xenforo-api-consumer')); ?></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php _e('New User Subscribed', 'xenforo-api-consumer'); ?></th>
                    <td>
                        <p>
                            <?php !empty($meta['user0Subscription'])
                                ? _e('Yes', 'xenforo-api-consumer')
                                : _e('No', 'xenforo-api-consumer'); ?>
                        </p>
                        <p class="description"><?php echo sprintf(
                            __('With new user subscription, new XenForo account will be sync\'d '
                                . 'immediately to WordPress if <a href="%1$s">Admin Account</a> is available (currently: %2$s) '
                                . 'and XenForo <a href="%3$s">login credentials</a> are accepted (currently: %4$s).', 'xenforo-api-consumer'),
                                admin_url('options-general.php?page=xfac&tab=api'),
                                intval(get_option('xfac_xf_admin_account')) > 0
                                    ? __('Yes', 'xenforo-api-consumer')
                                    : __('No', 'xenforo-api-consumer'),
                                admin_url('options-general.php?page=xfac&tab=user_role'),
                                !!get_option('xfac_sync_password')
                                    ? __('Yes', 'xenforo-api-consumer')
                                    : __('No', 'xenforo-api-consumer')
                            ); ?></p>
                    </td>
                </tr>
            <?php endif; ?>

        </table>

        <?php submit_button(); ?>
    </form>

</div>