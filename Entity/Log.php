<?php

namespace Xfrocks\Api\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Log
 * @package Xfrocks\Api\Entity
 *
 * @property int log_id
 * @property string client_id
 * @property int user_id
 * @property string ip_address
 * @property int request_date
 * @property string request_method
 * @property string request_uri
 * @property array request_data
 * @property int response_code
 * @property string response_output
 */
class Log extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bdapi_log';
        $structure->primaryKey = 'log_id';
        $structure->shortName = 'Xfrocks\Api:Log';
        $structure->columns = [
            'log_id' => ['type' => self::UINT, 'nullable' => true, 'autoIncrement' => true],
            'client_id' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'ip_address' => ['type' => self::STR, 'maxLength' => 50, 'required' => true],
            'request_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'request_method' => ['type' => self::STR, 'maxLength' => 10, 'required' => true],
            'request_uri' => ['type' => self::STR, 'required' => true],
            'request_data' => ['type' => self::SERIALIZED_ARRAY, 'default' => []],
            'response_code' => ['type' => self::STR, 'default' => 0],
            'response_output' => ['type' => self::SERIALIZED_ARRAY, 'default' => []]
        ];

        $structure->relations = [
            'User' => [
                'type' => self::TO_ONE,
                'entity' => 'XF:User',
                'conditions' => 'user_id',
                'primary' => true
            ]
        ];

        return $structure;
    }
}
