<?php

namespace Xfrocks\Api\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Structure;

/**
 * @property int token_id
 * @property string client_id
 * @property string token_text
 * @property int expire_date
 * @property int user_id
 * @property string scope
 * @property User User
 */
class Token extends TokenWithScope
{
    public function getText()
    {
        return $this->token_text;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_token';
        $structure->shortName = 'Xfrocks\Api:Token';
        $structure->primaryKey = 'token_id';
        $structure->columns = [
            'token_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'token_text' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'expire_date' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'scope' => ['type' => self::STR, 'required' => true]
        ];
        $structure->getters = ['scopes' => true];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
