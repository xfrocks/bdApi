<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

function xfac_show_user_profile($wfUser)
{
	$config = xfac_option_getConfig();
	if (empty($config))
	{
		return;
	}

	$apiRecords = xfac_user_getApiRecordsByUserId($wfUser->ID);

	$connectUrl = site_url('wp-login.php?xfac=authorize&redirect_to=' . rawurlencode(admin_url('profile.php')), 'login_post');

?>

<h3><?php _e('Connected Account', 'xenforo-api-consumer'); ?></h3>

<table class="form-table">

<?php if (empty($apiRecords)): ?>
	<tr>
		<th>&nbsp;</th>
		<td>
			<p>
				<a href="<?php echo $connectUrl; ?>"><?php _e('Connect to an account', 'xenforo-api-consumer'); ?></a>
			</p>
		</td>
	</tr>
<?php endif; ?>

<?php foreach ($apiRecords as $apiRecord): ?>
	<tr>
		<th>
			<?php if (!empty($apiRecord->profile['links']['avatar'])): ?>
			<img src="<?php echo $apiRecord->profile['links']['avatar']; ?>" width="32" style="float: left" />

			<div style="margin-left: 36px">
			<?php else: ?>
			<div>
			<? endif; ?>

				<a href="<?php echo $apiRecord->profile['links']['permalink']; ?>" target="_blank"><?php echo $apiRecord->profile['username']; ?></a><br />
				<?php echo $apiRecord->profile['user_email']; ?>
			</div>
		</th>
		<td valign="top">
			<p><a href="profile.php?xfac=disconnect&id=<?php echo $apiRecord->id; ?>"><?php _e('Disconnect this account', 'xenforo-api-consumer'); ?></a></p>
		</td>
	</tr>
<?php endforeach; ?>

</table>

<?php
}

add_action('show_user_profile', 'xfac_show_user_profile');

function xfac_dashboardProfile_admin_init()
{
	if (!defined('IS_PROFILE_PAGE'))
	{
		return;
	}
	
	if (empty($_REQUEST['xfac']))
	{
		return;
	}
	
	switch ($_REQUEST['xfac'])
	{
		case 'disconnect':
			if (empty($_REQUEST['id']))
			{
				return;
			}
			
			$wpUser = wp_get_current_user();
			if (empty($wpUser))
			{
				// huh?!
				return;
			}
			
			$apiRecords = xfac_user_getApiRecordsByUserId($wpUser->ID);
			$requestedRecord = false;
			foreach ($apiRecords as $apiRecord)
			{
				if ($apiRecord->id == $_REQUEST['id'])
				{
					$requestedRecord = $apiRecord;
				}
			}
			if (empty($requestedRecord))
			{
				return;
			}
			
			xfac_user_deleteAuthById($requestedRecord->id);
			wp_redirect('profile.php?xfac=disconnected');
			exit();
			break;
	}
}
add_action('admin_init', 'xfac_dashboardProfile_admin_init');