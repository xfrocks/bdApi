<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

?>

<?php echo $before_widget; ?>
<?php if ($title) echo $before_title . $title . $after_title; ?>
<ul>
<?php foreach($threads as $thread): ?>
	<li>
		<a href="<?php echo($thread['links']['permalink']) ?>">
			<?php echo($thread['thread_title']) ?>
		</a>
		<span class="post-date"><?php echo date_i18n(get_option('date_format'), $thread['thread_create_date']) ?></span>
	</li>
<?php endforeach; ?>
</ul>
<?php echo $after_widget; ?>