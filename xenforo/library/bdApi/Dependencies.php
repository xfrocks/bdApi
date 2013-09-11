<?php

class bdApi_Dependencies_Base extends XenForo_Dependencies_Public
{
	public function preLoadData()
	{
		if (class_exists('bdApi_Link'))
		{
			// trigger auto loading of bdApi_Link
		}

		$this->_dataPreLoadFromRegistry += array(
				// TODO
		);

		parent::preLoadData();
	}

	protected function _handleCustomPreloadedData(array &$data)
	{
		// setup our routes
		$routes = array();
		bdApi_Route_PrefixApi::setupRoutes($routes);
		XenForo_Link::setHandlerInfoForGroup(bdApi_Link::API_LINK_GROUP, $routes);

		// map the public route to a different group
		// then empty the array (so XenForo_Dependencies_Public won't be
		// able to map it again). We are doing this to invalidate all
		// attempt to call XenForo_Link::buildPublicLink(). The correct
		// method to call is bdApi_Link::buildPublicLink()
		XenForo_Link::setHandlerInfoForGroup(bdApi_Link::PUBLIC_LINK_GROUP, $data['routesPublic']);

		// sondh@2013-03-19
		// do not empty the routes public array, it will cause problem for other add-on that
		// expects its route to exist (like XenPorta)
		// $data['routesPublic'] = array();

		$response = parent::_handleCustomPreloadedData($data);

		// new approach to disable XenForo_Link::buildPublicLink()
		// let everything set, we will set it back to empty array later
		XenForo_Link::setHandlerInfoForGroup('public', array());
	}

	public function getNotFoundErrorRoute()
	{
		return array('bdApi_ControllerApi_Error', 'ErrorNotFound');
	}

	public function getServerErrorRoute()
	{
		return array('bdApi_ControllerApi_Error', 'ErrorServer');
	}

	public function getViewRenderer(Zend_Controller_Response_Http $response, $responseType, Zend_Controller_Request_Http $request)
	{
		/*
		 $renderer = parent::getViewRenderer($response, $responseType, $request);

		if (!$renderer OR $renderer instanceof XenForo_ViewRenderer_HtmlPublic)
		{
		// change the default renderer
		$renderer = new bdApi_ViewRenderer_Json($this, $response, $request);
		}
		elseif ($renderer instanceof XenForo_ViewRenderer_Xml)
		{
		// force to use our own xml renderer
		$renderer = new bdApi_ViewRenderer_Xml($this, $response, $request);
		}
		elseif ($renderer instanceof XenForo_ViewRenderer_Json)
		{
		// force to use our own json renderer
		$renderer = new bdApi_ViewRenderer_Json($this, $response, $request);
		}

		return $renderer;
		*/

		// because the oauth2-php library only supports JSON
		// it makes little sense for us to support anything else
		// so for now, we will only use the JSON renderer...
		// TODO: support XML?
		return new bdApi_ViewRenderer_Json($this, $response, $request);
	}

	public function getBaseViewClassName()
	{
		return 'bdApi_ViewApi_Base';
	}
	
	protected function _bdApi_reRoute(Zend_Controller_Request_Http $request, $routeMatch)
	{
		if (!empty($routeMatch))
		{
			$controllerName = $routeMatch->getControllerName();

			switch ($controllerName)
			{
				case 'bdApi_ControllerApi_Error':
					// ignore
					break;
				default:
					$action = $routeMatch->getAction();
					if (empty($action))
					{
						$action = 'index';
					}

					$method = $request->getMethod();

					$routeMatch->setAction($method . '-' . $action);
			}
		}

		return $routeMatch;
	}
}

if (XenForo_Application::$versionId > 1020000)
{
	class bdApi_Dependencies extends bdApi_Dependencies_Base
	{
		public function route(Zend_Controller_Request_Http $request, $routePath = null)
		{
			$routeMatch = parent::route($request, $routePath);

			$routeMatch = $this->_bdApi_reRoute($request, $routeMatch);

			return $routeMatch;
		}

		public function getRouter()
		{
			$router = new XenForo_Router();
			$router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix')->addRule(new bdApi_Route_PrefixApi(bdApi_Link::API_LINK_GROUP), 'Prefix');

			return $router;
		}

	}

}
else
{
	class bdApi_Dependencies extends bdApi_Dependencies_Base
	{

		public function route(Zend_Controller_Request_Http $request)
		{
			$router = new XenForo_Router();
			$router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix')->addRule(new bdApi_Route_PrefixApi(bdApi_Link::API_LINK_GROUP), 'Prefix');

			$routeMatch = $router->match($request);

			$routeMatch = $this->_bdApi_reRoute($request, $routeMatch);

			return $routeMatch;
		}

	}

}