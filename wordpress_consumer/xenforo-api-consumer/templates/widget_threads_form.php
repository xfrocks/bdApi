<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

?>

<p>
	<label for="<?php echo $this->get_field_id('title'); ?>">
		<?php _e('Title:'); ?>
	</label>
	<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
</p>

<p>
	<label for="<?php echo $this->get_field_id('forumIds'); ?>">
		<?php _e('Forums:', 'xenforo-api-consumer'); ?>
	</label>
	<select class="widefat" id="<?php echo $this->get_field_id('forumIds'); ?>" name="<?php echo $this->get_field_name('forumIds'); ?>[]" multiple="multiple" rows="5">
		<?php if (!empty($forums['forums'])): ?>
			<?php foreach($forums['forums'] as $forum): ?>
				<option value="<?php echo $forum['forum_id']; ?>"<?php if (in_array($forum['forum_id'], $forumIds)) echo ' selected="selected"'; ?>><?php echo $forum['forum_title']; ?></option>
			<?php endforeach; ?>
		<?php endif; ?>
	</select>
</p>

<p>
	<label for="<?php echo $this->get_field_id('type'); ?>">
		<?php _e('Type:', 'xenforo-api-consumer'); ?>
	</label>
	<select class="widefat" id="<?php echo $this->get_field_id('type'); ?>" name="<?php echo $this->get_field_name('type'); ?>">
		<?php foreach($availableTypes as $typeValue => $typeText): ?>
			<option value="<?php echo $typeValue; ?>"<?php if ($type == $typeValue) echo ' selected="selected"'; ?>><?php echo $typeText; ?></option>
		<?php endforeach; ?>
	</select>
</p>

<p>
	<label for="<?php echo $this->get_field_id('limit'); ?>">
		<?php _e('Limit:', 'xenforo-api-consumer'); ?>
	</label>
	<input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" />
</p>