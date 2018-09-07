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

        /** @var Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');

        if ($this->isInsert()) {
            $subRepo->pingUser('insert', $this);
        }
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');
        $subRepo->pingUser('delete', $this);

        if ($this->app()->options()->bdApi_subscriptionUser
            || $this->app()->options()->bdApi_subscriptionUserNotification
        ) {
            $subRepo->deleteSubscriptionsForTopic(
                Subscription::TYPE_USER,
                $this->user_id
            );
        }
    }
}
