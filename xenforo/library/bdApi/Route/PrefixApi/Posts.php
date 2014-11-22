<?php

class bdApi_Route_PrefixApi_Posts extends bdApi_Route_PrefixApi_Abstract
{
    const PREFIX_ATTACHMENTS = 'attachments';
    const PARAM_ATTACHMENT_ID = 'attachment_id';

    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        $action = $router->resolveActionWithIntegerParam($routePath, $request, 'post_id');

        $prefixAttachments = sprintf('%s/', self::PREFIX_ATTACHMENTS);
        if (strpos($action, $prefixAttachments) === 0) {
            $request->setParam(self::PARAM_ATTACHMENT_ID, intval(substr($action, strlen($prefixAttachments))));
            $action = self::PREFIX_ATTACHMENTS;
        }

        return $router->getRouteMatch('bdApi_ControllerApi_Post', $action);
    }

    public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
    {
        if ($action === self::PREFIX_ATTACHMENTS AND isset($extraParams[self::PARAM_ATTACHMENT_ID])) {
            $action .= sprintf('/%d', $extraParams[self::PARAM_ATTACHMENT_ID]);
            unset($extraParams[self::PARAM_ATTACHMENT_ID]);
        }

        return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'post_id');
    }

}
