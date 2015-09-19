<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit();
}

class XFAC_Widget_Search extends WP_Widget
{
    function __construct()
    {
        parent::__construct(false, __('XenForo Search', 'xenforo-api-consumer'), array(
            'classname' => 'widget_xfac_search widget_links',
            'description' => __('A list of search results from XenForo.'
                . ' This widget will only show up within WordPress search result first page.')
        ));
    }

    function form($instance)
    {
        $title = !empty($instance['title']) ? esc_attr($instance['title']) : '';

        if (empty($instance['limit'])) {
            $limit = 5;
        } else {
            $limit = esc_attr($instance['limit']);
        }

        require(xfac_template_locateTemplate('widget_search_form.php'));
    }

    function update($newInstance, $oldInstance)
    {
        $instance = $oldInstance;

        $instance['title'] = strip_tags($newInstance['title']);
        $instance['limit'] = intval($newInstance['limit']);

        return $instance;
    }

    function widget($args, $instance)
    {
        if (!is_search()) {
            return '';
        }

        /** @var WP_Query $wp_query */
        global $wp_query;

        extract($args);

        $limit = (!empty($instance['limit'])) ? absint($instance['limit']) : 5;
        if (empty($limit)) {
            $limit = 5;
        }

        $title = (!empty($instance['title'])) ? $instance['title'] : __('Forum Results');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);

        $config = xfac_option_getConfig();
        $threads = array();

        if (!empty($config)) {
            $accessToken = xfac_user_getSystemAccessToken($config, true);
            $results = xfac_api_postSearchThreads($config, $accessToken, $wp_query->get('s'), $limit);

            if (!empty($results['data'])) {
                $threads = $results['data'];
            }
        }

        require(xfac_template_locateTemplate('widget_search.php'));
    }

}

add_action('widgets_init', create_function('', 'return register_widget("XFAC_Widget_Search");'));
