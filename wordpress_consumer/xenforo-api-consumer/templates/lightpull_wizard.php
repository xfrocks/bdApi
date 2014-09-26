<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}
?>

<div class="wrap">
	<div id="icon-options-general" class="icon32">
		<br />
	</div>

	<h2><?php _e('Lightpull Wizard', 'xenforo-api-consumer'); ?></h2>

	<p><?php echo call_user_func_array('sprintf', array(
		__('Enter the API information or <a href="%s">click here</a> to start the automated process. You may need to log in to your Lightpull account.'),
		admin_url('options-general.php?page=xfac&do=lightpull_wizard')
	));
	 ?></p>

	<form method="post" action="options.php" id="xfacDashboardOptions">
		<?php settings_fields('xfac'); ?>

		<input name="xfac_root" type="hidden" id="xfac_root" value="<?php echo esc_attr(XFAC_LIGHTPULL_ROOT); ?>" />
		<table class="form-table">
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

		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes'); ?>"  />
		</p>
	</form>
</div>