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
		static $forums = null;

		$config = xfac_option_getConfig();

		if (!empty($config) AND $forums === null)
		{
			$forums = xfac_api_getForums($config);
		}

		$availableTypes = $this->_getAvailableTypes();

		$title = !empty($instance['title']) ? esc_attr($instance['title']) : '';
		$type = !empty($instance['type']) ? esc_attr($instance['type']) : '';

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

		require (xfac_template_locateTemplate('widget_threads_form.php'));
	}

	function update($newInstance, $oldInstance)
	{
		$instance = $oldInstance;

		$instance['title'] = strip_tags($newInstance['title']);

		$instance['forumIds'] = array();
		if (!empty($newInstance['forumIds']) AND is_array($newInstance['forumIds']))
		{
			foreach ($newInstance['forumIds'] as $forumId)
			{
				if (is_numeric($forumId))
				{
					$instance['forumIds'][] = $forumId;
				}
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

		wp_cache_delete(__CLASS__);

		return $instance;
	}

	function widget($args, $instance)
	{
		$cache = wp_cache_get(__CLASS__);
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
		if (empty($instance['type']) OR !isset($availableTypes[$instance['type']]))
		{
			$tmp = array_keys($availableTypes);
			$instance['type'] = reset($tmp);
		}

		if ($title === false)
		{
			$title = $availableTypes[$instance['type']];
		}
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);

		$config = xfac_option_getConfig();
		$threads = array();

		if (!empty($config) AND !empty($instance['forumIds']))
		{
			$forumId = implode(',', $instance['forumIds']);

			$extraParams = array(
				'_xfac' => 'threads.php',
				'limit' => $limit,
			);

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
					// this is the default order
					// $extraParams['order'] = 'thread_create_date_reverse';
					break;
			}
			$extraParams = http_build_query($extraParams);

			$results = xfac_api_getThreadsInForum($config, $forumId, 1, '', $extraParams);

			if (!empty($results['threads']))
			{
				$threads = $results['threads'];
			}
		}

		require (xfac_template_locateTemplate('widget_threads.php'));

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set(__CLASS__, $cache);
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
