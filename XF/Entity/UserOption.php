<?php

namespace Xfrocks\Api\XF\Entity;

use XF\Mvc\Entity\Structure;

class UserOption extends XFCP_UserOption
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $options = \XF::options();

        /** @var string $userColumn */
        $userColumn = $options->bdApi_subscriptionColumnUser;
        /** @var string $userNotifyColumn */
        $userNotifyColumn = $options->bdApi_subscriptionColumnUserNotification;

        if ($options->bdApi_subscriptionUser && $userColumn !== '') {
            $structure->columns[$userColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        if ($options->bdApi_subscriptionUserNotification && $userNotifyColumn !== '') {
            $structure->columns[$userNotifyColumn] = ['type' => self::SERIALIZED_ARRAY, 'default' => []];
        }

        return $structure;
    }
}
