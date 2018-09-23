<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Entity\User;
use XF\Mvc\Entity\Finder;

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
        if (!($entity instanceof \Xfrocks\Api\Entity\Token)) {
            return parent::getEntityHint($entity);
        }

        return $entity->scope;
    }

    public function getEntityExplain($entity)
    {
        if (!($entity instanceof \Xfrocks\Api\Entity\Token)) {
            return parent::getEntityExplain($entity);
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
}
