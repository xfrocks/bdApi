<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_options_init()
{
	$root = get_option('xfac_root');
	$clientId = get_option('xfac_client_id');
	$clientSecret = get_option('xfac_client_secret');

	$tagForumMappings = get_option('xfac_tag_forum_mappings');
	$tags = get_terms('post_tag', array('hide_empty' => false));

	if (!empty($root) AND !empty($clientId) AND !empty($clientSecret))
	{	
		$forums = xfac_api_getForums($root, $clientId, $clientSecret);
	}
	else
	{
		$forums = null;
	}
?>

<div class="wrap">
	<div id="icon-options-general" class="icon32">
		<br />
	</div><h2>General Settings</h2>

	<form method="post" action="options.php">
		<?php settings_fields('xfac'); ?>

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="xfac_root"><?php _e('API Root', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_root" type="text" id="xfac_root" value="<?php echo esc_attr($root); ?>" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="xfac_client_id"><?php _e('Client ID', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_client_id" type="text" id="xfac_client_id" value="<?php echo esc_attr($clientId); ?>" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="xfac_client_secret"><?php _e('Client Secret', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_client_secret" type="text" id="xfac_client_secret" value="<?php echo esc_attr($clientSecret); ?>" class="regular-text" />
				</td>
			</tr>

			<tr valign="top">
				<th scope="row"><label for="xfac_client_secret"><?php _e('Tag / Forum Mappings', 'xenforo-api-consumer'); ?></label></th>
				<td>
					<?php
						foreach(array_values($tagForumMappings) as $i => $tagForumMapping)
						{
							if (empty($tagForumMapping['term_id']) OR empty($tagForumMapping['forum_id']))
							{
								continue;
							}

							_xfac_options_renderTagForumMapping($tags, $forums, $i, $tagForumMapping);
						}
						
						if (!empty($tags) AND !empty($forums))
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
<div class="<?php echo ($tagForumMapping ? 'TagForumMapping_Record' : 'TagForumMapping_Template'); ?>" data-i="<?php echo $i; ?>">
	<select name="xfac_tag_forum_mappings[<?php echo $i; ?>][term_id]">
		<option value="0">&nbsp;</option>
		<?php foreach ($tags as $tag): ?>
			<option value="<?php echo esc_attr($tag->term_id); ?>"<?php if (!empty($tagForumMapping['term_id']) AND $tagForumMapping['term_id'] == $tag->term_id) echo ' selected="selected"'; ?>><?php echo esc_html($tag->name); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="xfac_tag_forum_mappings[<?php echo $i; ?>][forum_id]">
		<option value="0">&nbsp;</option>
		<?php foreach ($forums['forums'] as $forum): ?>
			<option value="<?php echo esc_attr($forum['forum_id']); ?>"<?php if (!empty($tagForumMapping['term_id']) AND $tagForumMapping['forum_id'] == $forum['forum_id']) echo ' selected="selected"'; ?>><?php echo esc_html($forum['forum_title']); ?></option>
		<?php endforeach; ?>
	</select>
</div>
<?php
}
