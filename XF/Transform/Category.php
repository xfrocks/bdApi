<?php

namespace Xfrocks\Api\XF\Transform;

class Category extends AbstractNode
{
    protected function getNameSingular()
    {
        return 'category';
    }

    protected function getRoutePrefix()
    {
        return 'categories';
    }
}
