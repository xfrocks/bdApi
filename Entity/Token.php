<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null token_id
 * @property string client_id
 * @property string token_text
 * @property int expire_date
 * @property int user_id
 * @property string scope
 *
 * GETTERS
 * @property string[] scopes
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property Client|null Client
 */
class Token extends TokenWithScope
{
    public function getText()
    {
        return $this->token_text;
    }

    public function getEntityColumnLabel($columnName)
    {
        switch ($columnName) {
            case 'client_id':
                return \XF::phrase('bdapi_client_id');
            case 'token_text':
                return \XF::phrase('bdapi_token_text');
            case 'expire_date':
                return \XF::phrase('bdapi_expire_date');
            case 'user_id':
                return \XF::phrase('user_name');
        }

        return null;
    }

    public function getEntityLabel()
    {
        return $this->token_text;
    }

    protected function _postSave()
    {
        if ($this->isChanged('scope')) {
            $this->updateUserScopes();
        }
    }

    protected function updateUserScopes()
    {
        $values = [];
        foreach ($this->getScopes() as $scope) {
            $values[] = sprintf(
                '(%s, %d, %s, %d)',
                $this->db()->quote($this->get('client_id')),
                $this->get('user_id'),
                $this->db()->quote($scope),
                \XF::$time
            );
        }
        if (count($values) === 0) {
            return;
        }

        $this->db()->query('
            INSERT IGNORE INTO `xf_bdapi_user_scope`
            (`client_id`, `user_id`, `scope`, `accept_date`)
            VALUES ' . implode(', ', $values) . ' 
        ');
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
