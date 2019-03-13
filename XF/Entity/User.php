<?php

namespace Xfrocks\Api\XF\Entity;

use Xfrocks\Api\Repository\Subscription;

class User extends XFCP_User
{
    /**
     * @return void
     */
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert()) {
            /** @var Subscription $subRepo */
            $subRepo = $this->repository('Xfrocks\Api:Subscription');
            $subRepo->pingUser('insert', $this);
        }
    }

    /**
     * @return void
     */
    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');
        $subRepo->pingUser('delete', $this);
    }
}
