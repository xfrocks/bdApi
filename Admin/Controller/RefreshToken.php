<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Entity\User;
use Xfrocks\Api\Entity\RefreshToken as EntityRefreshToken;

class RefreshToken extends Entity
{
    protected function getShortName()
    {
        return 'Xfrocks\Api:RefreshToken';
    }

    protected function getPrefixForPhrases()
    {
        return 'bdapi_refresh_token';
    }

    protected function getRoutePrefix()
    {
        return 'api-refresh-token';
    }

    public function getEntityHint($entity)
    {
        /** @var EntityRefreshToken $refreshToken */
        $refreshToken = $entity;
        /** @var User|null $user */
        $user = $refreshToken->User;

        return $user ? $user->username : '';
    }
}
