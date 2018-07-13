<?php

namespace Xfrocks\Api\Controller;

class Forum extends AbstractNode
{
    protected function getNodeTypeId()
    {
        return 'Forum';
    }

    protected function getNamePlural()
    {
        return 'forums';
    }

    protected function getNameSingular()
    {
        return 'forum';
    }
}
