<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Structure;
use Xfrocks\Api\OAuth2\Server;

/**
 * COLUMNS
 * @property int auth_code_id
 * @property string auth_code_text
 * @property string client_id
 * @property int user_id
 * @property int expire_date
 * @property string redirect_uri
 * @property string scope
 *
 * GETTERS
 * @property string[] scopes
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \Xfrocks\Api\Entity\Client Client
 */
class AuthCode extends TokenWithScope
{
    /**
     * @return string
     */
    public function getText()
    {
        return $this->auth_code_text;
    }

    /**
     * @param string $columnName
     * @return \XF\Phrase|null
     */
    public function getEntityColumnLabel($columnName)
    {
        switch ($columnName) {
            case 'auth_code_text':
            case 'client_id':
            case 'expire_date':
            case 'redirect_uri':
            case 'scope':
                return \XF::phrase('bdapi_' . $columnName);
            case 'user_id':
                return \XF::phrase('user_name');
        }

        return null;
    }

    /**
     * @return string
     */
    public function getEntityLabel()
    {
        return $this->auth_code_text;
    }

    public static function getStructure(Structure $structure)
    {
        /** @var Server $apiServer */
        $apiServer = \XF::app()->container('api.server');

        $structure->table = 'xf_bdapi_auth_code';
        $structure->shortName = 'Xfrocks\Api:AuthCode';
        $structure->primaryKey = 'auth_code_id';
        $structure->columns = [
            'auth_code_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'auth_code_text' => [
                'type' => self::STR,
                'maxLength' => 255,
                'default' => $apiServer->generateSecureKey(),
                'writeOnce' => true,
            ],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'expire_date' => ['type' => self::UINT, 'default' => \XF::$time + $apiServer->getOptionAuthCodeTTL()],
            'redirect_uri' => ['type' => self::STR, 'required' => true],
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
