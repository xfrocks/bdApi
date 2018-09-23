<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Entity\User;
use XF\Mvc\Entity\Finder;

class Client extends Entity
{
    protected function getShortName()
    {
        return 'Xfrocks\Api:Client';
    }

    protected function getPrefixForPhrases()
    {
        return 'bdapi_client';
    }

    protected function getRoutePrefix()
    {
        return 'api-clients';
    }

    public function getEntityHint($entity)
    {
        if (!($entity instanceof \Xfrocks\Api\Entity\Client)) {
            return parent::getEntityHint($entity);
        }

        return $entity->client_id;
    }

    public function getEntityExplain($entity)
    {
        if (!($entity instanceof \Xfrocks\Api\Entity\Client)) {
            return parent::getEntityHint($entity);
        }

        /** @var User|null $user */
        $user = $entity->User;
        return ($user ? $user->username : '') . ' - ' . $entity->description;
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
