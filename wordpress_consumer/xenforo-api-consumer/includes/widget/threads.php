<?php

// Exit if accessed directly
if (!defined('ABSPATH'))
{
	exit();
}

class XFAC_Widget_Threads extends WP_Widget
{
	function XFAC_Widget_Threads()
	{
		parent::WP_Widget(false, $name = __('XenForo Threads', 'xenforo-api-consumer'));
	}

	function form($instance)
	{
		$root = get_option('xfac_root');
		$clientId = get_option('xfac_client_id');
		$clientSecret = get_option('xfac_client_secret');

		if (!empty($root) AND !empty($clientId) AND !empty($clientSecret))
		{
			$forums = xfac_api_getForums($root, $clientId, $clientSecret);
		}
		else
		{
			$forums = null;
		}

		$availableTypes = $this->_getAvailableTypes();

		$title = esc_attr($instance['title']);
		$type = esc_attr($instance['type']);

		$forumIds = array();
		if (!empty($instance['forumIds']))
		{
			$forumIds = $instance['forumIds'];
		}

		if (empty($instance['limit']))
		{
			$limit = 5;
		}
		else
		{
			$limit = esc_attr($instance['limit']);
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
		<?php if (!empty($forums)): ?>
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

<?php
	}

	function update($newInstance, $oldInstance)
	{
		$instance = $oldInstance;
		
		$instance['title'] = strip_tags($newInstance['title']);
		
		$instance['forumIds'] = array();
		foreach ($newInstance['forumIds'] as $forumId)
		{
			if (is_numeric($forumId))
			{
				$instance['forumIds'][] = $forumId;
			}
		}
		
		$availableTypeValues = array_keys($this->_getAvailableTypes());
		if (in_array($newInstance['type'], $availableTypeValues))
		{
			$instance['type'] = $newInstance['type'];
		}
		else
		{
			$instance['type'] = reset($availableTypeValues);
		}

		$instance['limit'] = intval($newInstance['limit']);

		return $instance;
	}

	function widget($args, $instance)
	{
		$cache = wp_cache_get(__CLASS__, 'widget');

		if (!is_array($cache))
		{
			$cache = array();
		}

		if (empty($args['widget_id']))
		{
			$args['widget_id'] = $this->id;
		}

		if (isset($cache[$args['widget_id']]))
		{
			echo $cache[$args['widget_id']];
			return;
		}
		
		ob_start();
		extract($args);

		$limit = (!empty($instance['limit'])) ? absint($instance['limit']) : 5;
		if (empty($limit))
		{
			$limit = 5;
		}
		
		$title = (!empty($instance['title'])) ? $instance['title'] : false;
		$availableTypes = $this->_getAvailableTypes();
		if (empty($instance['type']) OR !in_array($instance['type'], $availableTypes))
		{
			$tmp = array_keys($availableTypes);
			$instance['type'] = reset($tmp);
		}
		
		if ($title === false)
		{
			$title = $availableTypes[$instance['type']];
		}
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);
		
		$root = get_option('xfac_root');
		$clientId = get_option('xfac_client_id');
		$clientSecret = get_option('xfac_client_secret');
		$threads = array();

		if (!empty($root) AND !empty($clientId) AND !empty($clientSecret) AND !empty($instance['forumIds']))
		{
			$forumId = implode(',', $instance['forumIds']);
			
			$extraParams = array('limit' => $limit);
			switch ($instance['type'])
			{
				case 'recent':
					$extraParams['order'] = 'thread_update_date_reverse';
					break;
				case 'most_viewed':
					$extraParams['order'] = 'thread_view_count_reverse';
					break;
				case 'most_replied':
					$extraParams['order'] = 'thread_post_count_reverse';
					break;
				case 'new':
				default:
					$extraParams['order'] = 'thread_create_date_reverse';
			}
			$extraParams = http_build_query($extraParams);
			
			$results = xfac_api_getThreadsInForum($root, $clientId, $clientSecret, $forumId, 1, '', $extraParams);
			
			if (!empty($results['threads']))
			{
				$threads = $results['threads'];
			}
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
<?php
		
		$cache[$args['widget_id']] = ob_get_flush();
		//wp_cache_set(__CLASS__, $cache, 'widget');
	}
	
	protected function _getAvailableTypes()
	{
		return array(
			'new' => __('New Threads', 'xenforo-api-consumer'),
			'recent' => __('New Replies', 'xenforo-api-consumer'),
			'most_viewed' => __('Most Viewed Threads', 'xenforo-api-consumer'),
			'most_replied' => __('Most Replied Threads', 'xenforo-api-consumer'),
		);
	}

}

add_action('widgets_init', create_function('', 'return register_widget("XFAC_Widget_Threads");'));
