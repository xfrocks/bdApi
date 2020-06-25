<?php

namespace Xfrocks\Api\Admin\Controller;

use Xfrocks\Api\Entity\Subscription as EntitySubscription;

class Subscription extends Entity
{
    public function getEntityExplain($entity)
    {
        /** @var \Xfrocks\Api\Entity\Client|null $client */
        $client = $entity->getRelation('Client');
        return $client !== null ? $client->name : '';
    }

    public function getEntityHint($entity)
    {
        /** @var EntitySubscription $subscription */
        $subscription = $entity;
        return $subscription->callback;
    }

    protected function getShortName()
    {
        return 'Xfrocks\Api:Subscription';
    }

    protected function getPrefixForPhrases()
    {
        return 'bdapi_subscription';
    }

    protected function getRoutePrefix()
    {
        return 'api-subscriptions';
    }
}
