<?php

namespace Xfrocks\Api\Controller;

class Page extends AbstractNode
{
    protected function getNamePlural()
    {
        return 'pages';
    }

    protected function getNameSingular()
    {
        return 'page';
    }

    protected function getNodeTypeId()
    {
        return 'Page';
    }
}
