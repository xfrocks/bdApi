<?php

namespace Xfrocks\Api\Controller;

class Category extends AbstractNode
{
    protected function getNamePlural()
    {
        return 'categories';
    }

    protected function getNameSingular()
    {
        return 'category';
    }

    protected function getNodeTypeId()
    {
        return 'Category';
    }
}
