<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

?>

<p>
    <label for="<?php echo $this->get_field_id('title'); ?>">
        <?php _e('Title:'); ?>
    </label>
    <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
           name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>"/>
</p>

<p>
    <label for="<?php echo $this->get_field_id('limit'); ?>">
        <?php _e('Limit:', 'xenforo-api-consumer'); ?>
    </label>
    <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>"
           name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>"/>
</p>