<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Mvc\ParameterBag;

abstract class Entity extends \Xfrocks\Api\DevHelper\Admin\Controller\Entity
{
    /**
     * @param \XF\Mvc\Entity\Entity $entity
     * @return string
     */
    protected function getEntityClientAndUser($entity)
    {
        /** @var \Xfrocks\Api\Entity\Client $client */
        $client = $entity->getRelation('Client');

        /** @var \XF\Entity\User|null $user */
        $user = $entity->getRelation('User');
        if ($user === null) {
            return $client->name;
        }

        return sprintf('%s / %s', $client->name, $user->username);
    }

    protected function getPrefixForClasses()
    {
        return 'Xfrocks\Api';
    }

    protected function getPrefixForTemplates()
    {
        return 'bdapi';
    }

    protected function preDispatchType($action, ParameterBag $params)
    {
        parent::preDispatchType($action, $params);

        $this->assertAdminPermission('bdApi');
    }
}
