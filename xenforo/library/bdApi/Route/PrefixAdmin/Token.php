<?php

class bdApi_Route_PrefixAdmin_Token implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		if (in_array($routePath, array(
			'add',
			'save'
		)))
		{
			$action = $routePath;
		}
		else
		{
			$action = $router->resolveActionWithIntegerParam($routePath, $request, 'token_id');
		}
		return $router->getRouteMatch('bdApi_ControllerAdmin_Token', $action, 'bdApi');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (is_array($data))
		{
			return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'token_id');
		}
		else
		{
			return XenForo_Link::buildBasicLink($outputPrefix, $action, $extension);
		}
	}

}
