<?php

class bdApi_Route_PrefixAdmin_Log implements XenForo_Route_Interface
{

    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        if (in_array($routePath, array(
            'add',
            'save'
        ))) {
            $action = $routePath;
        } else {
            $action = $router->resolveActionWithIntegerParam($routePath, $request, 'log_id');
        }
        return $router->getRouteMatch('bdApi_ControllerAdmin_Log', $action, 'bdApi');
    }

    public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
    {
        if (is_array($data)) {
            return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'log_id');
        } else {
            return XenForo_Link::buildBasicLink($outputPrefix, $action, $extension);
        }
    }

}
