<?php

namespace Xfrocks\Api\XF\Entity;

use Xfrocks\Api\Repository\Subscription;

class User extends XFCP_User
{
    /**
     * @return bool
     */
    public function canAddApiClient()
    {
        if ($this->hasPermission('general', 'bdApi_clientNew')) {
            return true;
        }

        if ($this->is_admin) {
            return true;
        }

        return false;
    }

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
