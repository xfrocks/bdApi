<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Mvc\ParameterBag;

abstract class Entity extends \Xfrocks\Api\DevHelper\Admin\Controller\Entity
{
    protected function preDispatchType($action, ParameterBag $params)
    {
        parent::preDispatchType($action, $params);

        $this->assertAdminPermission('bdApi');
    }

    protected function getPrefixForClasses()
    {
        return 'Xfrocks\Api';
    }

    protected function getPrefixForTemplates()
    {
        return 'bdapi';
    }
}
