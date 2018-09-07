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

        $options = \XF::options();

        $userColumn = $options->bdApi_subscriptionColumnUser;
        $userNotifyColumn = $options->bdApi_subscriptionColumnUserNotification;

        if ($options->bdApi_subscriptionUser
            && !empty($userColumn)
        ) {
            $structure->columns[$userColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        if ($options->bdApi_subscriptionUserNotification
            && !empty($userNotifyColumn)
        ) {
            $structure->columns[$userNotifyColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        return $structure;
    }
}
