<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Entity\User;
use Xfrocks\Api\Entity\Token as EntityToken;

class Token extends Entity
{
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

    public function getEntityHint($entity)
    {
        /** @var EntityToken $token */
        $token = $entity;
        return $token->scope;
    }

    public function getEntityExplain($entity)
    {
        /** @var EntityToken $token */
        $token = $entity;
        /** @var User|null $user */
        $user = $token->User;

        return $user ? $user->username : '';
    }
}
