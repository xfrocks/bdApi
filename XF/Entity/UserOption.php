<?php

namespace Xfrocks\Api\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * Class UserOption
 * @package Xfrocks\Api\XF\Entity
 * @inheritdoc
 */
class UserOption extends XFCP_UserOption
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $userColumn = \XF::options()->bdApi_subscriptionColumnUser;
        $userNotifyColumn = \XF::options()->bdApi_subscriptionColumnUserNotification;

        if (!empty($userColumn)) {
            $structure->columns[$userColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        if (!empty($userNotifyColumn)) {
            $structure->columns[$userNotifyColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        return $structure;
    }
}
