<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Structure;
use Xfrocks\Api\OAuth2\Server;

/**
 * COLUMNS
 * @property int|null token_id
 * @property string token_text
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
class Token extends TokenWithScope
{
    public function getText()
    {
        return $this->token_text;
    }

    /**
     * @param string $columnName
     * @return \XF\Phrase|null
     */
    public function getEntityColumnLabel($columnName)
    {
        switch ($columnName) {
            case 'client_id':
            case 'expire_date':
            case 'scope':
            case 'token_text':
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
        return $this->token_text;
    }

    /**
     * @return void
     */
    protected function _postSave()
    {
        if ($this->isChanged('scope')) {
            $this->updateUserScopes();
        }
    }

    /**
     * @return void
     */
    protected function updateUserScopes()
    {
        $db = $this->db();

        $values = [];
        foreach ($this->getScopes() as $scope) {
            $values[] = sprintf(
                '(%s, %d, %s, %d)',
                $db->quote($this->get('client_id')),
                $this->get('user_id'),
                $db->quote($scope),
                \XF::$time
            );
        }
        if (count($values) === 0) {
            return;
        }

        $db->query('
            INSERT IGNORE INTO `xf_bdapi_user_scope`
            (`client_id`, `user_id`, `scope`, `accept_date`)
            VALUES ' . implode(', ', $values) . ' 
        ');
    }

    public static function getStructure(Structure $structure)
    {
        /** @var Server $apiServer */
        $apiServer = \XF::app()->container('api.server');

        $structure->table = 'xf_bdapi_token';
        $structure->shortName = 'Xfrocks\Api:Token';
        $structure->primaryKey = 'token_id';
        $structure->columns = [
            'token_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'token_text' => [
                'type' => self::STR,
                'maxLength' => 255,
                'default' => $apiServer->generateSecureKey(),
                'writeOnce' => true,
            ],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'expire_date' => ['type' => self::UINT, 'default' => \XF::$time + $apiServer->getOptionAccessTokenTTL()],
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
