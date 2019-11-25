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
        /** @var \Xfrocks\Api\Entity\Client|null $client */
        $client = $entity->getRelation('Client');
        if (!$client) {
            return '';
        }

        /** @var \XF\Entity\User|null $user */
        $user = $entity->getRelation('User');
        return $user ? sprintf('%s / %s', $client->name, $user->username) : $client->name;
    }

    protected function getPrefixForClasses()
    {
        return 'Xfrocks\Api';
    }

    protected function getPrefixForTemplates()
    {
        return 'bdapi';
    }

    /**
     * @param mixed $action
     * @param ParameterBag $params
     * @return void
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchType($action, ParameterBag $params)
    {
        parent::preDispatchType($action, $params);

        $this->assertAdminPermission('bdApi');
    }
}
