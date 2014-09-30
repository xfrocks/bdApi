<?php

class bdApi_Route_PrefixApi extends XenForo_Route_Prefix
{
	public function __construct($routeType)
	{
		$this->_routeType = $routeType;
	}

	/**
	 * Setups all default routes for [bd] Api. Also fires the code event
	 * `bdapi_setup_routes` and let any other add-ons to setup extra
	 * routes for the system.
	 *
	 * @param array $routes the target routes array
	 */
	public static function setupRoutes(array &$routes)
	{
		self::addRoute($routes, 'index', 'bdApi_Route_PrefixApi_Index');

		self::addRoute($routes, 'oauth', 'bdApi_Route_PrefixApi_OAuth');

		self::addRoute($routes, 'categories', 'bdApi_Route_PrefixApi_Categories', 'data_only');
		self::addRoute($routes, 'forums', 'bdApi_Route_PrefixApi_Forums', 'data_only');
		self::addRoute($routes, 'posts', 'bdApi_Route_PrefixApi_Posts', 'data_only');
		self::addRoute($routes, 'threads', 'bdApi_Route_PrefixApi_Threads', 'data_only');
		self::addRoute($routes, 'users', 'bdApi_Route_PrefixApi_Users', 'data_only');
		self::addRoute($routes, 'conversations', 'bdApi_Route_PrefixApi_Conversations', 'data_only');
		self::addRoute($routes, 'conversation-messages', 'bdApi_Route_PrefixApi_ConversationMessages', 'data_only');
		self::addRoute($routes, 'notifications', 'bdApi_Route_PrefixApi_Notifications');

		self::addRoute($routes, 'search', 'bdApi_Route_PrefixApi_Search');

		self::addRoute($routes, 'assets', 'bdApi_Route_PrefixApi_Assets');
		self::addRoute($routes, 'batch', 'bdApi_Route_PrefixApi_Batch');
		self::addRoute($routes, 'subscriptions', 'bdApi_Route_PrefixApi_Subscriptions');
		self::addRoute($routes, 'tools', 'bdApi_Route_PrefixApi_Tools');

		XenForo_CodeEvent::fire('api_setup_routes', array(&$routes));
	}

	/**
	 * Helper method to easily add new route to a routes array.
	 *
	 * @param array $routes the target routes array
	 * @param string $originalPrefix
	 * @param string $routeClass
	 * @param string $buildLink
	 */
	public static function addRoute(array &$routes, $originalPrefix, $routeClass, $buildLink = 'none')
	{
		$routes[$originalPrefix] = array(
			'route_class' => $routeClass,
			'build_link' => $buildLink,
		);
	}

}
