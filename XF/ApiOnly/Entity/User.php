<?php

namespace Xfrocks\Api\XF\ApiOnly\Entity;

use Xfrocks\Api\Listener;
use Xfrocks\Api\OAuth2\Server;
use Xfrocks\Api\XF\ApiOnly\Session\Session;

class User extends XFCP_User
{
    public function getAvatarUrl($sizeCode, $forceType = null, $canonical = false)
    {
        $url = parent::getAvatarUrl($sizeCode, $forceType, $canonical);

        if ($url === null) {
            $apiRouter = $this->app()->router(Listener::$routerType);
            $url = $apiRouter->buildLink('users/default-avatar', $this, ['size' => $sizeCode]);
        }

        return $url;
    }

    public function hasAdminPermission($permissionId)
    {
        /** @var Session $session */
        $session = $this->app()->session();
        if (!$session->hasScope(Server::SCOPE_MANAGE_SYSTEM)) {
            return false;
        }

        return parent::hasAdminPermission($permissionId);
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_User extends \XF\Entity\User
    {
        // extension hint
    }
}
