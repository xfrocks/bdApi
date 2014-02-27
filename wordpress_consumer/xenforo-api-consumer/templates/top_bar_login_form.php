<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}
?>

<form id="xfacTopBarLoginForm" action="<?php echo esc_url(site_url('wp-login.php?xfac=top_bar', 'login_post')); ?>" method="post">
	<p>
		<label for="user_login">
			<?php _e('Username', 'xenforo-api-consumer') ?><br />
			<input type="text" name="user_login" id="user_login" class="input" size="20" />
 		</label>
	</p>
	<p>
		<label for="user_pass">
			<?php _e('Password', 'xenforo-api-consumer') ?><br />
			<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" />
		</label>
	</p>

	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large"
			value="<?php esc_attr_e('Log in', 'xenforo-api-consumer'); ?>" />
	</p>
</form>
