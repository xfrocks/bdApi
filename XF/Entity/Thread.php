<?php

namespace Xfrocks\Api\XF\Entity;

use XF\Mvc\Entity\Structure;
use Xfrocks\Api\Repository\Subscription;

/**
 * Class Thread
 * @package Xfrocks\Api\XF\Entity
 * @inheritdoc
 */
class Thread extends XFCP_Thread
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $subColumn = \XF::options()->bdApi_subscriptionColumnThreadPost;
        if (!empty($subColumn)) {
            $structure->columns[$subColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        return $structure;
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        /** @var Subscription $subRepo */
        $subRepo = \XF::repository('Xfrocks\Api:Subscription');
        $subRepo->deleteSubscriptionsForTopic(
            Subscription::TYPE_THREAD_POST,
            $this->thread_id
        );
    }
}
