<?php

namespace Xfrocks\Api\Admin\Controller;

use Xfrocks\Api\Entity\AuthCode as EntityAuthCode;

class AuthCode extends Entity
{
    public function getEntityExplain($entity)
    {
        return $this->getEntityClientAndUser($entity);
    }

    public function getEntityHint($entity)
    {
        /** @var EntityAuthCode $authCode */
        $authCode = $entity;
        return $authCode->scope;
    }

    protected function getShortName()
    {
        return 'Xfrocks\Api:AuthCode';
    }

    protected function getPrefixForPhrases()
    {
        return 'bdapi_auth_code';
    }

    protected function getRoutePrefix()
    {
        return 'api-auth-codes';
    }
}
