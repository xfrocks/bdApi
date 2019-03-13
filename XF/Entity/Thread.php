<?php

namespace Xfrocks\Api\XF\Entity;

use XF\Mvc\Entity\Structure;
use Xfrocks\Api\Repository\Subscription;

class Thread extends XFCP_Thread
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $options = \XF::options();

        /** @var int $sub */
        $sub = $options->bdApi_subscriptionThreadPost;
        /** @var string $subColumn */
        $subColumn = $options->bdApi_subscriptionColumnThreadPost;

        if ($sub > 0 && $subColumn !== '') {
            $structure->columns[$subColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        return $structure;
    }

    /**
     * @return void
     */
    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var int $sub */
        $sub = $this->app()->options()->bdApi_subscriptionThreadPost;

        if ($sub > 0) {
            /** @var Subscription $subRepo */
            $subRepo = $this->repository('Xfrocks\Api:Subscription');
            $subRepo->deleteSubscriptionsForTopic(Subscription::TYPE_THREAD_POST, $this->thread_id);
        }
    }
}
