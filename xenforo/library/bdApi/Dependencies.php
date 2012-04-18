<?php

class bdApi_Dependencies extends XenForo_Dependencies_Public
{
	public function preLoadData()
	{
		$this->_dataPreLoadFromRegistry += array(
		);
		
		parent::preLoadData();
		
		// setup our special code event listeners
		XenForo_CodeEvent::addListener('front_controller_pre_dispatch', array(__CLASS__, 'front_controller_pre_dispatch'));
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
		$data['routesPublic'] = array();
		
		return parent::_handleCustomPreloadedData($data);
	}
	
	public function route(Zend_Controller_Request_Http $request)
	{
		$router = new XenForo_Router();
		$router->addRule(new XenForo_Route_ResponseSuffix(), 'ResponseSuffix')
		       ->addRule(new bdApi_Route_PrefixApi(bdApi_Link::API_LINK_GROUP), 'Prefix');

		return $router->match($request);
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
	
	public static function front_controller_pre_dispatch(XenForo_FrontController $fc, XenForo_RouteMatch &$routeMatch)
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
				
				$request = $fc->getRequest();
				$method = $request->getMethod();
				
				$routeMatch->setAction($method . '-' . $action);
		}
	}
}