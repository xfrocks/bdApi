<?php

namespace Xfrocks\Api\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Structure;

/**
 * @property int auth_code_id
 * @property string client_id
 * @property string auth_code_text
 * @property string redirect_uri
 * @property int expire_date
 * @property int user_id
 * @property string scope
 * @property User User
 */
class AuthCode extends TokenWithScope
{
    public function getText()
    {
        return $this->auth_code_text;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_auth_code';
        $structure->shortName = 'Xfrocks\Api:AuthCode';
        $structure->primaryKey = 'auth_code_id';
        $structure->columns = [
            'auth_code_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'auth_code_text' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'redirect_uri' => ['type' => self::STR, 'required' => true],
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
