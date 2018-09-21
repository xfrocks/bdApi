<?php

namespace Xfrocks\Api\Entity;

use League\OAuth2\Server\Util\SecureKey;
use XF\Mvc\Entity\Structure;
use Xfrocks\Api\Util\Vendor;

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

    public function getEntityColumnLabel($columnName)
    {
        switch ($columnName) {
            case 'client_id':
                return \XF::phrase('bdapi_client_id');
            case 'refresh_token_text':
                return \XF::phrase('bdapi_refresh_token_text');
            case 'expire_date':
                return \XF::phrase('bdapi_expire_date');
            case 'user_id':
                return \XF::phrase('user_name');
        }

        return null;
    }

    public function getEntityLabel()
    {
        return $this->refresh_token_text;
    }

    public static function getStructure(Structure $structure)
    {
        Vendor::load();

        $structure->table = 'xf_bdapi_refresh_token';
        $structure->shortName = 'Xfrocks\Api:RefreshToken';
        $structure->primaryKey = 'refresh_token_id';
        $structure->columns = [
            'refresh_token_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'refresh_token_text' => ['type' => self::STR, 'maxLength' => 255, 'default' => SecureKey::generate()],
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
