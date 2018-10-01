<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property string client_id
 * @property int user_id
 * @property string scope
 * @property int accept_date
 */
class UserScope extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_user_scope';
        $structure->shortName = 'Xfrocks\Api:UserScope';
        $structure->primaryKey = ['client_id', 'user_id', 'scope'];
        $structure->columns = [
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'readOnly' => true],
            'user_id' => ['type' => self::UINT, 'readOnly' => true],
            'scope' => ['type' => self::STR, 'maxLength' => 255, 'readOnly' => true],
            'accept_date' => ['type' => self::UINT, 'readOnly' => true]
        ];

        return $structure;
    }
}
