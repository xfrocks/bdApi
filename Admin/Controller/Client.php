<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Entity\User;
use Xfrocks\Api\Entity\Client as EntityClient;

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
        /** @var EntityClient $client */
        $client = $entity;
        /** @var User|null $user */
        $user = $client->User;

        $parts = [];

        if ($user !== null) {
            $parts[] = $user->username;
        }

        if (strlen($client->description) > 0) {
            $parts[] = $client->description;
        }

        return implode(' - ', $parts);
    }
}
