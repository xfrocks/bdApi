<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Util\Random;

class AuthCode extends Entity
{
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

    protected function entityAddEdit($entity)
    {
        if ($entity instanceof \Xfrocks\Api\Entity\AuthCode
            && !$entity->auth_code_id
            && empty($entity->auth_code_text)
        ) {
            $entity->auth_code_text = Random::getRandomString(32);
        }

        return parent::entityAddEdit($entity);
    }
}
