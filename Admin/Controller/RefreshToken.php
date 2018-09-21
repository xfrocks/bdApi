<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Entity\User;
use XF\Mvc\Entity\Finder;
use XF\Util\Random;

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
        if (!($entity instanceof \Xfrocks\Api\Entity\RefreshToken)) {
            return parent::getEntityHint($entity);
        }

        /** @var User|null $user */
        $user = $entity->User;

        return $user ? $user->username : '';
    }

    protected function entityListData()
    {
        $data = parent::entityListData();
        if ($data[0] instanceof Finder) {
            $data[0]->with('User');
        }

        return $data;
    }

    protected function entityAddEdit($entity)
    {
        if ($entity instanceof \Xfrocks\Api\Entity\RefreshToken
            && !$entity->refresh_token_id
            && empty($entity->refresh_token_text)
        ) {
            $entity->refresh_token_text = Random::getRandomString(40);
        }

        return parent::entityAddEdit($entity);
    }
}
