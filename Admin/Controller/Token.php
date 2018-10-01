<?php

namespace Xfrocks\Api\Admin\Controller;

use Xfrocks\Api\Entity\Token as EntityToken;

class Token extends Entity
{
    public function getEntityExplain($entity)
    {
        return $this->getEntityClientAndUser($entity);
    }

    public function getEntityHint($entity)
    {
        /** @var EntityToken $token */
        $token = $entity;
        return $token->scope;
    }

    protected function getShortName()
    {
        return 'Xfrocks\Api:Token';
    }

    protected function getPrefixForPhrases()
    {
        return 'bdapi_token';
    }

    protected function getRoutePrefix()
    {
        return 'api-tokens';
    }
}
