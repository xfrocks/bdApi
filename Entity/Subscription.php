<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Subscription
 * @package Xfrocks\Api\Entity
 *
 * @property int subscription_id
 * @property string client_id
 * @property string callback
 * @property string topic
 * @property int subscribe_date
 * @property int expire_date
 */
class Subscription extends Entity
{
    const OPTION_UPDATE_CALLBACKS = 'updateCallbacks';

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_subscription';
        $structure->primaryKey = 'subscription_id';
        $structure->shortName = 'Xfrocks\Api:Subscription';

        $structure->columns = [
            'subscription_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'client_id' => ['type' => self::STR, 'required' => true, 'maxLength' => 255],
            'callback' => ['type' => self::STR, 'required' => true],
            'topic' => ['type' => self::STR, 'required' => true, 'maxLength' => 255],
            'subscribe_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'expire_date' => ['type' => self::UINT, 'default' => 0]
        ];

        $structure->options = [
            self::OPTION_UPDATE_CALLBACKS => true
        ];

        return $structure;
    }

    protected function _postSave()
    {
        if ($this->getOption(self::OPTION_UPDATE_CALLBACKS)) {
            $this->subscriptionRepo()->updateCallbacksForTopic($this->topic);
        }
    }

    protected function _postDelete()
    {
        if ($this->getOption(self::OPTION_UPDATE_CALLBACKS)) {
            $this->subscriptionRepo()->updateCallbacksForTopic($this->topic);
        }
    }

    /**
     * @return \Xfrocks\Api\Repository\Subscription
     */
    protected function subscriptionRepo()
    {
        /** @var \Xfrocks\Api\Repository\Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');

        return $subRepo;
    }
}
