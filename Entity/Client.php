<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Arr;

/**
 * COLUMNS
 * @property string name
 * @property string description
 * @property int user_id
 * @property string redirect_uri
 * @property string client_id
 * @property string client_secret
 * @property array options
 *
 * RELATIONS
 * @property \XF\Entity\User User
 */
class Client extends Entity
{
    public function canEdit()
    {
        $visitor = \XF::visitor();
        if (!$visitor->user_id) {
            return false;
        }

        return $this->user_id === $visitor->user_id;
    }

    public function isValidRedirectUri($redirectUri)
    {
        if (empty($redirectUri) || !is_string($redirectUri)) {
            return false;
        }

        if ($redirectUri === $this->redirect_uri) {
            return true;
        }

        if (empty($this->options['whitelisted_domains'])) {
            return false;
        }

        $parsed = parse_url($redirectUri);
        if (empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        $domains = explode("\n", $this->options['whitelisted_domains']);
        foreach ($domains as $domain) {
            if (empty($domain)) {
                continue;
            }

            $pattern = '#^';
            for ($i = 0, $l = utf8_strlen($domain); $i < $l; $i++) {
                $char = utf8_substr($domain, $i, 1);
                if ($char === '*') {
                    $pattern .= '.+';
                } else {
                    $pattern .= preg_quote($char, '#');
                }
            }
            $pattern .= '$#';
            if (preg_match($pattern, $parsed['host'])) {
                return true;
            }
        }

        return false;
    }

    public function setClientOptions(array $options)
    {
        $this->set('options', Arr::mapMerge($this->options ?: [], $options));
    }

    public function getEntityColumnLabel($columnName)
    {
        switch ($columnName) {
            case 'client_id':
            case 'client_secret':
            case 'redirect_uri':
                return \XF::phrase('bdapi_' . $columnName);
            case 'name':
            case 'description':
                return \XF::phrase('bdapi_client_' . $columnName);
            case 'user_id':
                return \XF::phrase('user_name');
        }

        return null;
    }

    public function getEntityLabel()
    {
        return $this->name;
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_client';
        $structure->shortName = 'Xfrocks\Api:Client';
        $structure->primaryKey = 'client_id';
        $structure->columns = [
            'name' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'description' => ['type' => self::STR, 'required' => true],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'redirect_uri' => ['type' => self::STR, 'required' => true],
            'client_id' => [
                'type' => self::STR,
                'default' => \XF::generateRandomString(10),
                'maxLength' => 255,
                'unique' => true,
                'writeOnce' => true,
            ],
            'client_secret' => [
                'type' => self::STR,
                'default' => \XF::generateRandomString(15),
                'maxLength' => 255,
            ],
            'options' => ['type' => self::SERIALIZED_ARRAY]
        ];
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

    protected function _postDelete()
    {
        $db = $this->db();

        $db->delete('xf_bdapi_auth_code', 'client_id = ?', $this->client_id);
        $db->delete('xf_bdapi_refresh_token', 'client_id = ?', $this->client_id);
        $db->delete('xf_bdapi_token', 'client_id = ?', $this->client_id);
        $db->delete('xf_bdapi_user_scope', 'client_id = ?', $this->client_id);

        $this
            ->app()
            ->jobManager()
            ->enqueueUnique('bdapi_' . $this->client_id, 'Xfrocks\Api\Job\ClientDelete', [
                'clientId' => $this->client_id
            ]);
    }
}
