<?php

namespace Xfrocks\Api\Admin\Controller;

use Xfrocks\Api\Entity\Client as EntityClient;

class Client extends Entity
{
    public function getEntityExplain($entity)
    {
        /** @var EntityClient $client */
        $client = $entity;
        $user = $client->User;
        return $user !== null ? $user->username : '';
    }

    public function getEntityHint($entity)
    {
        /** @var EntityClient $client */
        $client = $entity;
        return $client->redirect_uri;
    }

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

    protected function supportsAdding()
    {
        return false;
    }
}
