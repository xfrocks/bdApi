<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_options_init()
{
	$config = xfac_option_getConfig();
	$hourlyNext = wp_next_scheduled('xfac_cron_hourly');
	
	$xfGuestRecords = xfac_user_getApiRecordsByUserId(0);
	
	$tagForumMappings = get_option('xfac_tag_forum_mappings');
	if (!is_array($tagForumMappings))
	{
		$tagForumMappings = array();
	}
	
	$optionTopBarForums = get_option('xfac_top_bar_forums');
	if (!is_array($optionTopBarForums))
	{
		$optionTopBarForums = array();
	}

	$tags = get_terms('post_tag', array('hide_empty' => false));

	if (!empty($config))
	{
		$meta = xfac_option_getMeta($config);
		$forums = $meta['forums'];
	}
	else
	{
		$forums = null;
	}
?>

<div class="wrap">
	<div id="icon-options-general" class="icon32">
		<br />
	</div><h2><?php _e('XenForo API Consumer', 'xenforo-api-consumer'); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields('xfac'); ?>

		<table class="form-table">
			<?php if (xfac_option_getWorkingMode() === 'network'): ?>
			<tr valign="top">
				<th scope="row"><label for="xfac_root"><?php _e('API Root', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_root" type="text" id="xfac_root" value="<?php echo esc_attr($config['root']); ?>" class="regular-text" disabled="disabled" />
				</td>
			</tr>
			<?php else: ?>
			<tr valign="top">
				<th scope="row"><label for="xfac_root"><?php _e('API Root', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_root" type="text" id="xfac_root" value="<?php echo esc_attr($config['root']); ?>" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="xfac_client_id"><?php _e('API Key', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_client_id" type="text" id="xfac_client_id" value="<?php echo esc_attr($config['clientId']); ?>" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="xfac_client_secret"><?php _e('API Secret', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_client_secret" type="text" id="xfac_client_secret" value="<?php echo esc_attr($config['clientSecret']); ?>" class="regular-text" />
				</td>
			</tr>
			<?php endif; ?>

			<tr valign="top">
				<th scope="row">
					<?php _e('Synchronization', 'xenforo-api-consumer'); ?><br />
					
					<?php _e('Next Run', 'xenforo-api-consumer'); ?>:
					<?php echo date_i18n('H:i', $hourlyNext + get_option('gmt_offset') * HOUR_IN_SECONDS); ?>
					(<a href="options-general.php?page=xfac&cron=hourly"><?php _e('Sync Now', 'xenforo-api-consumer'); ?></a>)
				</th>
				<td>
					<fieldset>
						<label for="xfac_sync_post_wp_xf">
							<input name="xfac_sync_post_wp_xf" type="checkbox" id="xfac_sync_post_wp_xf" value="1" <?php checked('1', get_option('xfac_sync_post_wp_xf')); ?> />
							<?php _e('Post from WordPress to XenForo (as thread)', 'xenforo-api-consumer'); ?>
						</label>
					</fieldset>

					<fieldset>
						<label for="xfac_sync_post_xf_wp">
							<input name="xfac_sync_post_xf_wp" type="checkbox" id="xfac_sync_post_xf_wp" value="1" <?php checked('1', get_option('xfac_sync_post_xf_wp')); ?> />
							<?php _e('Thread from XenForo to WordPress (as draft post)', 'xenforo-api-consumer'); ?>
						</label>

						<div style="margin-left: 20px;">
							<label for="xfac_sync_post_xf_wp_publish">
								<input name="xfac_sync_post_xf_wp_publish" type="checkbox" id="xfac_sync_post_xf_wp_publish" value="1" <?php checked('1', get_option('xfac_sync_post_xf_wp_publish')); ?> />
								<?php _e('Auto-publish synchronized post', 'xenforo-api-consumer'); ?>
							</label>
						</div>
					</fieldset>

					<fieldset>
						<label for="xfac_sync_comment_wp_xf">
							<input name="xfac_sync_comment_wp_xf" type="checkbox" id="xfac_sync_comment_wp_xf" value="1" <?php checked('1', get_option('xfac_sync_comment_wp_xf')); ?> />
							<?php _e('Comment from WordPress to XenForo (as reply)', 'xenforo-api-consumer'); ?>
						</label>

						<div style="margin-left: 20px;">
							<label for="xfac_sync_comment_wp_xf_as_guest">
								<input name="xfac_sync_comment_wp_xf_as_guest" type="checkbox" id="xfac_sync_comment_wp_xf_as_guest" value="1" <?php checked('1', get_option('xfac_sync_comment_wp_xf_as_guest')); ?> />
								<?php _e('Sync as guest if account is not connected', 'xenforo-api-consumer'); ?>
							</label>
						</div>
					</fieldset>

					<fieldset>
						<label for="xfac_sync_comment_xf_wp">
							<input name="xfac_sync_comment_xf_wp" type="checkbox" id="xfac_sync_comment_xf_wp" value="1" <?php checked('1', get_option('xfac_sync_comment_xf_wp')); ?> />
							<?php _e('Reply from XenForo to WordPress (as comment)', 'xenforo-api-consumer'); ?>
						</label>

						<div style="margin-left: 20px;">
							<label for="xfac_sync_comment_xf_wp_as_guest">
								<input name="xfac_sync_comment_xf_wp_as_guest" type="checkbox" id="xfac_sync_comment_xf_wp_as_guest" value="1" <?php checked('1', get_option('xfac_sync_comment_xf_wp_as_guest')); ?> />
								<?php _e('Sync as guest if account is not connected', 'xenforo-api-consumer'); ?>
							</label>
						</div>
					</fieldset>

					<fieldset>
						<label for="xfac_sync_avatar_xf_wp">
							<input name="xfac_sync_avatar_xf_wp" type="checkbox" id="xfac_sync_avatar_xf_wp" value="1" <?php checked('1', get_option('xfac_sync_avatar_xf_wp')); ?> />
							<?php _e('Avatar from XenForo to WordPress', 'xenforo-api-consumer'); ?>
						</label>
					</fieldset>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					<?php _e('Top Bar', 'xenforo-api-consumer'); ?>
				</th>
				<td>
					<fieldset>
						<label for="xfac_top_bar_forums_0">
							<input name="xfac_top_bar_forums[]" type="checkbox" id="xfac_top_bar_forums_0" value="0" <?php checked(true, in_array(0, $optionTopBarForums)); ?> />
							<?php _e('Show Forums link', 'xenforo-api-consumer'); ?>
						</label>

						<div style="margin-left: 20px;">
							<?php foreach ($forums as $forum): ?>
								<label for="xfac_top_bar_forums_<?php echo $forum['forum_id']; ?>">
									<input name="xfac_top_bar_forums[]" type="checkbox" id="xfac_top_bar_forums_<?php echo $forum['forum_id']; ?>" value="<?php echo $forum['forum_id']; ?>" <?php checked(true, in_array($forum['forum_id'], $optionTopBarForums)); ?> />
									<?php echo $forum['forum_title']; ?>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<fieldset>
						<label for="xfac_top_bar_notifications">
							<input name="xfac_top_bar_notifications" type="checkbox" id="xfac_top_bar_notifications" value="1" <?php checked('1', get_option('xfac_top_bar_notifications')); ?> />
							<?php _e('Show Alerts link', 'xenforo-api-consumer'); ?>
						</label>
					</fieldset>
					<fieldset>
						<label for="xfac_top_bar_conversations">
							<input name="xfac_top_bar_conversations" type="checkbox" id="xfac_top_bar_conversations" value="1" <?php checked('1', get_option('xfac_top_bar_conversations')); ?> />
							<?php _e('Show Conversations link', 'xenforo-api-consumer'); ?>
						</label>
					</fieldset>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					<?php _e('XenForo Guest Account', 'xenforo-api-consumer'); ?>
				</th>
				<td>
					<?php if (!empty($config)): ?>
					<?php $callbackUrl = admin_url('options-general.php?page=xfac&do=xfac_xf_guest_account'); ?> 
					<?php $authorizeUrl = xfac_api_getAuthorizeUrl($config, $callbackUrl); ?>
					<?php endif; ?>

					<?php if (!empty($xfGuestRecords)): ?>
						<?php foreach($xfGuestRecords as $xfGuestRecord): ?>
							<label for="xfac_xf_guest_account_<?php echo $xfGuestRecord->id; ?>">
								<input name="xfac_xf_guest_account" type="checkbox" id="xfac_xf_guest_account_<?php echo $xfGuestRecord->id; ?>" value="<?php echo $xfGuestRecord->id; ?>" <?php checked($xfGuestRecord->id, get_option('xfac_xf_guest_account')); ?> />
								<?php echo $xfGuestRecord->profile['username']; ?>
								<?php if (!empty($authorizeUrl)): ?>
								(<a href="<?php echo $authorizeUrl; ?>"><?php _e('change', 'xenforo-api-consumer'); ?></a>)
								<?php endif; ?>
							</label>
							<p class="description"><?php _e('The guest account will be used when contents need to be sync\'d but no connected account can be found.', 'xenforo-api-consumer'); ?></p>
						<?php endforeach; ?>
					<?php else: ?>
					<label for="xfac_xf_guest_account">
						<input name="xfac_xf_guest_account" type="hidden" value="0" />
						<input name="xfac_xf_guest_account" type="checkbox" id="xfac_xf_guest_account" value="1" disabled="disabled" />

						<?php if (!empty($authorizeUrl)): ?>
						<a href="<?php echo $authorizeUrl; ?>"><?php _e('Connect a XenForo account as Guest account', 'xenforo-api-consumer'); ?></a>
						<?php else: ?>
						<?php _e('Configure API Client first', 'xenforo-api-consumer'); ?>
						<?php endif; ?>
					</label>
					<?php endif; ?>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="xfac_tag_forum_mappings"><?php _e('Tag / Forum Mappings', 'xenforo-api-consumer'); ?></label></th>
				<td>
					<?php
						foreach (array_values($tagForumMappings) as $i => $tagForumMapping)
						{
							if (empty($tagForumMapping['term_id']) OR empty($tagForumMapping['forum_id']))
							{
								continue;
							}

							_xfac_options_renderTagForumMapping($tags, $forums, $i, $tagForumMapping);
						}

						if (empty($tags))
						{
							_e('No WordPress tags found', 'xenforo-api-consumer');
						}
						elseif (empty($forums))
						{
							_e('No XenForo forums found', 'xenforo-api-consumer');
						}
						else
						{
							_xfac_options_renderTagForumMapping($tags, $forums, ++$i, null);
						}
					?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>"  />
		</p>
	</form>

</div>

<?php
}

function _xfac_options_renderTagForumMapping($tags, $forums, $i, $tagForumMapping)
{
	// generate fake forum in case we lost connection
	if (empty($forums['forums']) AND !empty($tagForumMapping['forum_id']))
	{
		$forums = array('forums' => array(array(
			'forum_id' => $tagForumMapping['forum_id'],
			'forum_title' => '#' . $tagForumMapping['forum_id'],
		)));
	}
?>
<div class="<?php echo($tagForumMapping ? 'TagForumMapping_Record' : 'TagForumMapping_Template'); ?>" data-i="<?php echo $i; ?>">
	<select name="xfac_tag_forum_mappings[<?php echo $i; ?>][term_id]">
		<option value="0">&nbsp;</option>
		<?php foreach ($tags as $tag): ?>
			<option value="<?php echo esc_attr($tag->term_id); ?>"
				<?php if (!empty($tagForumMapping['term_id']) AND $tagForumMapping['term_id'] == $tag->term_id) echo ' selected="selected"';?>>
				<?php echo esc_html($tag->name); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<select name="xfac_tag_forum_mappings[<?php echo $i; ?>][forum_id]">
		<option value="0">&nbsp;</option>
		<?php foreach ($forums['forums'] as $forum): ?>
			<option value="<?php echo esc_attr($forum['forum_id']); ?>"
				<?php if (!empty($tagForumMapping['term_id']) AND $tagForumMapping['forum_id'] == $forum['forum_id']) echo ' selected="selected"';?>>
				<?php echo esc_html($forum['forum_title']); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
<?php
}

function xfac_wpmu_options()
{
	$config = xfac_option_getConfig();
?>

<h3><?php _e('XenForo API Consumer', 'xenforo-api-consumer'); ?></h3>
<table id="xfac" class="form-table">
	<tr valign="top">
		<th scope="row"><label for="xfac_root"><?php _e('API Root', 'xenforo-api-consumer'); ?></label></th>
		<td>
		<input name="xfac_root" type="text" id="xfac_root" value="<?php echo esc_attr($config['root']); ?>" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="xfac_client_id"><?php _e('API Key', 'xenforo-api-consumer'); ?></label></th>
		<td>
		<input name="xfac_client_id" type="text" id="xfac_client_id" value="<?php echo esc_attr($config['clientId']); ?>" class="regular-text" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="xfac_client_secret"><?php _e('API Secret', 'xenforo-api-consumer'); ?></label></th>
		<td>
		<input name="xfac_client_secret" type="text" id="xfac_client_secret" value="<?php echo esc_attr($config['clientSecret']); ?>" class="regular-text" />
		</td>
	</tr>
</table>

<?php
}
add_action('wpmu_options', 'xfac_wpmu_options');

function xfac_update_wpmu_options()
{
	$options = array(
		'xfac_root',
		'xfac_client_id',
		'xfac_client_secret',
	);

	foreach ($options as $optionName)
	{
		if (!isset($_POST[$optionName]))
		{
			continue;
		}

		$optionValue = wp_unslash($_POST[$optionName]);
		update_site_option($optionName, $optionValue);
	}
}
add_action('update_wpmu_options', 'xfac_update_wpmu_options');

function xfac_dashboardOptions_admin_init()
{
	if (empty($_REQUEST['page']))
	{
		return;
	}
	if ($_REQUEST['page'] !== 'xfac')
	{
		return;
	}
	
	if (!empty($_REQUEST['cron']))
	{
		switch ($_REQUEST['cron'])
		{
			case 'hourly':
				do_action('xfac_cron_hourly');
				wp_redirect(admin_url('options-general.php?page=xfac&ran=hourly'));
				exit;
		}
	}
	elseif (!empty($_REQUEST['do']))
	{
		switch ($_REQUEST['do'])
		{
			case 'xfac_xf_guest_account':
				$config = xfac_option_getConfig();
				$callbackUrl = admin_url('options-general.php?page=xfac&do=xfac_xf_guest_account');

				if (empty($_REQUEST['code']))
				{
					wp_die('no_code');
				}
				$token = xfac_api_getAccessTokenFromCode($config, $_REQUEST['code'], $callbackUrl);

				if (empty($token))
				{
					wp_die('no_token');
				}
				$guest = xfac_api_getUsersMe($config, $token['access_token']);

				if (empty($guest['user']))
				{
					wp_die('no_xf_user');
				}
				xfac_user_updateAuth(0, $config['root'], $guest['user']['user_id'], $guest['user'], $token);
				wp_redirect(admin_url('options-general.php?page=xfac&done=xfac_xf_guest_account'));
				break;
		}
	}
}
add_action('admin_init', 'xfac_dashboardOptions_admin_init');
