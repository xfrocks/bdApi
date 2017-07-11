<?php

class bdApi_Route_PrefixApi_ProfilePosts extends bdApi_Route_PrefixApi_Abstract
{
    const PREFIX_COMMENTS = 'comments';
    const PARAM_COMMENT_ID = 'comment_id';

    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        $action = $router->resolveActionWithIntegerParam($routePath, $request, 'profile_post_id');

        $prefixComments = sprintf('%s/', self::PREFIX_COMMENTS);
        if (strpos($action, $prefixComments) === 0) {
            $request->setParam(self::PARAM_COMMENT_ID, intval(substr($action, strlen($prefixComments))));
            $action = self::PREFIX_COMMENTS;
        }

        return $router->getRouteMatch('bdApi_ControllerApi_ProfilePost', $action);
    }

    public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
    {
        if ($action === self::PREFIX_COMMENTS AND isset($extraParams[self::PARAM_COMMENT_ID])) {
            $action .= sprintf('/%d', $extraParams[self::PARAM_COMMENT_ID]);
            unset($extraParams[self::PARAM_COMMENT_ID]);
        }

        return XenForo_Link::buildBasicLinkWithIntegerParam(
            $outputPrefix,
            $action,
            $extension,
            $data,
            'profile_post_id'
        );
    }
}
