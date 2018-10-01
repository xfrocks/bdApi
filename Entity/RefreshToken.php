<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Structure;
use Xfrocks\Api\OAuth2\Server;

/**
 * COLUMNS
 * @property int|null refresh_token_id
 * @property string refresh_token_text
 * @property string client_id
 * @property int user_id
 * @property int expire_date
 * @property string scope
 *
 * GETTERS
 * @property string[] scopes
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \Xfrocks\Api\Entity\Client Client
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
            case 'expire_date':
            case 'refresh_token_text':
            case 'scope':
                return \XF::phrase('bdapi_' . $columnName);
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
        /** @var Server $apiServer */
        $apiServer = \XF::app()->container('api.server');

        $structure->table = 'xf_bdapi_refresh_token';
        $structure->shortName = 'Xfrocks\Api:RefreshToken';
        $structure->primaryKey = 'refresh_token_id';
        $structure->columns = [
            'refresh_token_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'refresh_token_text' => [
                'type' => self::STR,
                'maxLength' => 255,
                'default' => $apiServer->generateSecureKey(),
                'writeOnce' => true,
            ],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'expire_date' => ['type' => self::UINT, 'default' => \XF::$time + $apiServer->getOptionRefreshTokenTTL()],
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Client' => [
                'entity' => 'Xfrocks\Api:Client',
                'type' => self::TO_ONE,
                'conditions' => 'client_id',
                'primary' => true
            ]
        ];

        self::addDefaultTokenElements($structure);

        return $structure;
    }
}
