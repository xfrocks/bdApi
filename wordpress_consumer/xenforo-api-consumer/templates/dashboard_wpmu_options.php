<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

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
	
	<?php if (!empty($meta['linkIndex'])): ?>
	<tr valign="top">
		<th scope="row">&nbsp;</th>
		<td>
			<?php _e('Successfully connected to XenForo at:', 'xenforo-api-consumer'); ?>
			<a href="<?php echo esc_attr($meta['linkIndex']); ?>" target="_blank"><?php echo $meta['linkIndex']; ?></a>

			<p><?php echo xfac_api_getVersionSuggestionText($config, $meta); ?></p>
		</td>
	</tr>
	<?php endif; ?>
</table>