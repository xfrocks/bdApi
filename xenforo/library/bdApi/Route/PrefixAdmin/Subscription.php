<?php

class bdApi_Route_PrefixAdmin_Subscription implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'subscription_id');
		return $router->getRouteMatch('bdApi_ControllerAdmin_Subscription', $action, 'bdApi');
	}

	public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
	{
		if (is_array($data))
		{
			return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'subscription_id');
		}
		else
		{
			return XenForo_Link::buildBasicLink($outputPrefix, $action, $extension);
		}
	}

}
