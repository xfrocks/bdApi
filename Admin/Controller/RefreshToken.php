<?php

namespace Xfrocks\Api\Admin\Controller;

use Xfrocks\Api\Entity\RefreshToken as EntityRefreshToken;

class RefreshToken extends Entity
{
    public function getEntityExplain($entity)
    {
        return $this->getEntityClientAndUser($entity);
    }

    public function getEntityHint($entity)
    {
        /** @var EntityRefreshToken $refreshToken */
        $refreshToken = $entity;
        return $refreshToken->scope;
    }

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
        return 'api-refresh-tokens';
    }
}
