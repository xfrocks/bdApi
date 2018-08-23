<?php

namespace Xfrocks\Api\XF\ApiOnly\Entity;

class User extends XFCP_User
{
    public function getAvatarUrl($sizeCode, $forceType = null, $canonical = false)
    {
        $url = parent::getAvatarUrl($sizeCode, $forceType, $canonical);

        if ($url === null) {
            $url = $this->app()->router('api')->buildLink('users/default-avatar', $this, ['size' => $sizeCode]);
        }

        return $url;
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_User extends \XF\Entity\User
    {
        // extension hint
    }
}
