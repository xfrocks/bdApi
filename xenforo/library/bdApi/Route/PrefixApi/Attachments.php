<?php

class bdApi_Route_PrefixApi_Attachments extends bdApi_Route_PrefixApi_Abstract
{
    public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
    {
        $action = $router->resolveActionWithIntegerParam($routePath, $request, 'attachment_id');
        return $router->getRouteMatch('bdApi_ControllerApi_Attachment', $action);
    }

    public function buildLink($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams)
    {
        if ($action === 'data'
            && is_array($data)
            && !empty($data['viewUrl'])
        ) {
            $session = bdApi_Data_Helper_Core::safeGetSession();
            if (!empty($session)) {
                $oauthClient = $session->getOAuthClient();
                if (!empty($oauthClient['_isPublicSessionClient'])) {
                    return new XenForo_Link($data['viewUrl'], false);
                }
            }
        }

        return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'attachment_id');
    }
}
