<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}
?>

<form id="xfacTopBarLoginForm" action="<?php echo esc_url($loginFormAction); ?>" method="post">
	<p>
		<label for="login">
			<?php _e('Username', 'xenforo-api-consumer') ?><br />
			<input type="text" name="login" id="login" class="input" size="20" />
 		</label>
	</p>
	<p>
		<label for="password">
			<?php _e('Password', 'xenforo-api-consumer') ?><br />
			<input type="password" name="password" id="password" class="input" value="" size="20" />
		</label>
	</p>

	<p>
		<label for="remember">
			<input type="checkbox" name="remember" id="remember" value="1" />
			<?php _e('Stay logged in', 'xenforo-api-consumer') ?><br />
		</label>
	</p>

	<p class="submit">
		<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e('Log in', 'xenforo-api-consumer'); ?>" />
	</p>
	
	<input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>" />
</form>
