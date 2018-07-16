<?php

namespace Xfrocks\Api\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Arr;

/**
 * COLUMNS
 * @property string client_id
 * @property string client_secret
 * @property string redirect_uri
 * @property string name
 * @property string description
 * @property int user_id
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

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_client';
        $structure->shortName = 'Xfrocks\Api:Client';
        $structure->primaryKey = 'client_id';
        $structure->columns = [
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'client_secret' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'redirect_uri' => ['type' => self::STR, 'required' => true],
            'name' => ['type' => self::STR, 'maxLength' => 255, 'required' => true],
            'description' => ['type' => self::STR, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
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
}
