<?php

namespace Xfrocks\Api\XF\Entity;

use Xfrocks\Api\Repository\Subscription;

/**
 * Class User
 * @package Xfrocks\Api\XF\Entity
 * @inheritdoc
 */
class User extends XFCP_User
{
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert()) {
            /** @var Subscription $subRepo */
            $subRepo = $this->repository('Xfrocks\Api:Subscription');
            $subRepo->pingUser('insert', $this);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');
        $subRepo->pingUser('delete', $this);
    }
}
