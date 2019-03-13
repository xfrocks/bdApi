<?php

namespace Xfrocks\Api\XF\Entity;

use Xfrocks\Api\Repository\Subscription;

class Post extends XFCP_Post
{
    /**
     * @return void
     */
    protected function _postSave()
    {
        parent::_postSave();

        /** @var Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');

        if ($this->isInsert()) {
            $subRepo->pingThreadPost('insert', $this);
        } elseif ($this->isChanged('message_state')) {
            if ($this->message_state === 'visible') {
                $subRepo->pingThreadPost('insert', $this);
            } elseif ($this->getExistingValue('message') === 'visible') {
                $subRepo->pingThreadPost('delete', $this);
            }
        } else {
            $subRepo->pingThreadPost('update', $this);
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
        if ($this->message_state === 'visible') {
            $subRepo->pingThreadPost('delete', $this);
        }
    }
}
