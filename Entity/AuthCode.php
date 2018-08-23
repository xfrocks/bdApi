<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null auth_code_id
 * @property string client_id
 * @property string auth_code_text
 * @property string redirect_uri
 * @property int expire_date
 * @property int user_id
 * @property string scope
 *
 * GETTERS
 * @property string[] scopes
 *
 * RELATIONS
 * @property \XF\Entity\User User
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
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];

        self::addDefaultTokenElements($structure);

        return $structure;
    }
}
