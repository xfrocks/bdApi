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

        return $structure;
    }
}
