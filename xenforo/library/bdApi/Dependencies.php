<?php

class bdApi_Dependencies extends XenForo_Dependencies_Public
{
	public function preLoadData()
	{
		$this->_dataPreLoadFromRegistry += array(
		);
		
		parent::preLoadData();
	}
	
	protected function _handleCustomPreloadedData(array &$data)
	{
		// setup our routes
		$routes = array();
		bdApi_Route_Prefix::setupRoutes($routes);
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
		       ->addRule(new bdApi_Route_Prefix(bdApi_Link::API_LINK_GROUP), 'Prefix');

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
	}
	
	public function getBaseViewClassName()
	{
		return 'bdApi_ViewApi_Base';
	}
}