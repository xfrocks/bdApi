<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_options_init()
{
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
				<input name="xfac_root" type="text" id="xfac_root" value="<?php form_option('xfac_root'); ?>" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="xfac_client_id"><?php _e('Client ID', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_client_id" type="text" id="xfac_client_id" value="<?php form_option('xfac_client_id'); ?>" class="regular-text" />
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="xfac_client_secret"><?php _e('Client Secret', 'xenforo-api-consumer'); ?></label></th>
				<td>
				<input name="xfac_client_secret" type="text" id="xfac_client_secret" value="<?php form_option('xfac_client_secret'); ?>" class="regular-text" />
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
