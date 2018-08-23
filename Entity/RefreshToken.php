<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null refresh_token_id
 * @property string client_id
 * @property string refresh_token_text
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
class RefreshToken extends TokenWithScope
{
    public function getText()
    {
        return $this->refresh_token_text;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_refresh_token';
        $structure->shortName = 'Xfrocks\Api:RefreshToken';
        $structure->primaryKey = 'refresh_token_id';
        $structure->columns = [
            'refresh_token_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'refresh_token_text' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
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
