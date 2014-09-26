<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

?>

<h3><?php _e('Lightpull', 'xenforo-api-consumer'); ?></h3>
<table id="xfac" class="form-table">
	<tr valign="top">
		<th scope="row"><label for="xfac_client_id"><?php _e('API Key', 'xenforo-api-consumer'); ?></label></th>
		<td>
			<input name="xfac_root" type="hidden" id="xfac_root" value="<?php echo esc_attr(XFAC_LIGHTPULL_ROOT); ?>" />
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