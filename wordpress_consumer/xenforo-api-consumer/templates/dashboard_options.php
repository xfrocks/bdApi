<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function _xfac_dashboardOptions_renderTagForumMapping($tags, $forums, $i, $tagForumMapping)
{
	// generate fake forum in case we lost connection
	if (empty($forums) AND !empty($tagForumMapping['forum_id']))
	{
		$forums = array(array(
			'forum_id' => $tagForumMapping['forum_id'],
			'forum_title' => '#' . $tagForumMapping['forum_id'],
		));
	}
?>

<div class="<?php echo($tagForumMapping ? 'TagForumMapping_Record' : 'TagForumMapping_Template'); ?>" data-i="<?php echo $i; ?>">
	<select name="xfac_tag_forum_mappings[<?php echo $i; ?>][term_id]">
		<option value="0">&nbsp;</option>
		<?php foreach ($tags as $tag): ?>
			<option value="<?php echo esc_attr($tag->term_id); ?>"
				<?php
				if (!empty($tagForumMapping['term_id']) AND $tagForumMapping['term_id'] == $tag->term_id)
					echo ' selected="selected"';
			?>>
				<?php echo esc_html($tag->name); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<select name="xfac_tag_forum_mappings[<?php echo $i; ?>][forum_id]">
		<option value="0">&nbsp;</option>
		<?php foreach ($forums as $forum): ?>
			<option value="<?php echo esc_attr($forum['forum_id']); ?>"
				<?php
				if (!empty($tagForumMapping['term_id']) AND $tagForumMapping['forum_id'] == $forum['forum_id'])
					echo ' selected="selected"';
			?>>
				<?php echo esc_html($forum['forum_title']); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
<?php
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

			<?php if (!empty($meta['linkIndex'])): ?>
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

						<div style="margin-left: 20px;">
							<label for="xfac_sync_post_wp_xf_excerpt">
								<input name="xfac_sync_post_wp_xf_excerpt" type="checkbox" id="xfac_sync_post_wp_xf_excerpt" value="1" <?php checked('1', get_option('xfac_sync_post_wp_xf_excerpt')); ?> />
								<?php _e('Sync excerpt only', 'xenforo-api-consumer'); ?>
							</label>

							<br /><label for="xfac_sync_post_wp_xf_link">
								<input name="xfac_sync_post_wp_xf_link" type="checkbox" id="xfac_sync_post_wp_xf_link" value="1" <?php checked('1', get_option('xfac_sync_post_wp_xf_link')); ?> />
								<?php _e('Include post link', 'xenforo-api-consumer'); ?>
							</label>
						</div>
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

					<fieldset>
						<p><strong><?php _e('User Authentication', 'xenforo-api-consumer'); ?></strong></p>

						<div style="margin-left: 20px;">
							<label for="xfac_bypass_users_can_register">
								<input name="xfac_bypass_users_can_register" type="checkbox" id="xfac_bypass_users_can_register" value="1" <?php checked('1', get_option('xfac_bypass_users_can_register')); ?> />
								<?php _e('Always create new WordPress account for XenForo user (bypass "Anyone can register")', 'xenforo-api-consumer'); ?>
							</label>

							<br /><label for="xfac_sync_password">
								<input name="xfac_sync_password" type="checkbox" id="xfac_sync_password" value="1" <?php checked('1', get_option('xfac_sync_password')); ?> />
								<?php _e('Accept XenForo username/password for login', 'xenforo-api-consumer'); ?>
							</label>

							<br /><label for="xfac_sync_login">
								<input name="xfac_sync_login" type="checkbox" id="xfac_sync_login" value="1" <?php checked('1', get_option('xfac_sync_login')); ?> />
								<?php _e('Sync logged-in cookie', 'xenforo-api-consumer'); ?>
							</label>
						</div>
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

						<?php if (!empty($forums)): ?>
							<div style="margin-left: 20px;">
								<?php foreach ($forums as $forum): ?>
									<label for="xfac_top_bar_forums_<?php echo $forum['forum_id']; ?>">
										<input name="xfac_top_bar_forums[]" type="checkbox" id="xfac_top_bar_forums_<?php echo $forum['forum_id']; ?>" value="<?php echo $forum['forum_id']; ?>" <?php checked(true, in_array($forum['forum_id'], $optionTopBarForums)); ?> />
										<?php echo $forum['forum_title']; ?>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
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

					<fieldset>
						<label for="xfac_top_bar_replace">
							<input name="xfac_top_bar_replace" type="checkbox" id="xfac_top_bar_replace" value="1" <?php checked('1', get_option('xfac_top_bar_replace')); ?> />
							<?php _e('Replace Admin Bar', 'xenforo-api-consumer'); ?>
						</label>
						<p class="description"><?php _e('Enable to let the Top Bar replace WordPress Admin Bar completely (instead of merging together).', 'xenforo-api-consumer'); ?></p>
					</fieldset>
					<fieldset>
						<label for="xfac_top_bar_always">
							<input name="xfac_top_bar_always" type="checkbox" id="xfac_top_bar_always" value="1" <?php checked('1', get_option('xfac_top_bar_always')); ?> />
							<?php _e('Always Show', 'xenforo-api-consumer'); ?>
						</label>
						<p class="description"><?php _e('Enable to let the Top Bar appear even for guests.', 'xenforo-api-consumer'); ?></p>
					</fieldset>
				</td>
			</tr>

			<tr valign="top">
				<th scope="row">
					<?php _e('XenForo Guest Account', 'xenforo-api-consumer'); ?>
				</th>
				<td>
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

					$i = -1;
					foreach (array_values($tagForumMappings) as $i => $tagForumMapping)
					{
						if (empty($tagForumMapping['term_id']) OR empty($tagForumMapping['forum_id']))
						{
							continue;
						}

						_xfac_dashboardOptions_renderTagForumMapping($tags, $forums, $i, $tagForumMapping);
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
						_xfac_dashboardOptions_renderTagForumMapping($tags, $forums, ++$i, null);
					}
					?>
				</td>
			</tr>
			<?php endif; ?>

		</table>

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>"  />
		</p>
	</form>

</div>