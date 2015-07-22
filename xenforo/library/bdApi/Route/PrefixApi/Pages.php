<?php

class bdApi_Route_PrefixApi_Pages extends bdApi_Route_PrefixApi_Abstract
{
    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        $action = $router->resolveActionWithIntegerParam($routePath, $request, 'node_id');
        return $router->getRouteMatch('bdApi_ControllerApi_Page', $action);
    }

    public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
    {
        return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'node_id');
    }

}
