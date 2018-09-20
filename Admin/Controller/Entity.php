<?php

namespace Xfrocks\Api\Admin\Controller;

abstract class Entity extends \Xfrocks\Api\DevHelper\Admin\Controller\Entity
{
    protected function getPrefixForClasses()
    {
        return 'Xfrocks\Api';
    }

    protected function getPrefixForTemplates()
    {
        return 'bdapi';
    }
}
