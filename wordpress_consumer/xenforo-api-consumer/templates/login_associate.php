<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

?>

<?php login_header($title, '<p class="message">' . $message . '</p>'); ?>

<form id="associateform" action="<?php echo esc_url(site_url('wp-login.php?xfac=associate', 'login_post')); ?>" method="post">
	<p>
		<label for="user_login" >
			<?php _e('Username', 'xenforo-api-consumer') ?><br />
			<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr($wpUser->user_login); ?>" size="20" />
 		</label>
	</p>
	<p>
		<label for="user_pass">
			<?php _e('Password', 'xenforo-api-consumer') ?><br />
			<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" />
		</label>
	</p>

	<input type="hidden" name="xf_user[username]" value="<?php echo esc_attr($xfUser['username']) ?>" />
	<input type="hidden" name="refresh_token" value="<?php echo esc_attr($refreshToken) ?>" />
	<input type="hidden" name="scope" value="<?php echo esc_attr($scope) ?>" />
	<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo) ?>" />

	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large"
			value="<?php esc_attr_e('Associate Account', 'xenforo-api-consumer'); ?>" />
	</p>
</form>

<?php login_footer('user_login'); ?>