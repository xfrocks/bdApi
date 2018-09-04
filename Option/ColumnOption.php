<?php

namespace Xfrocks\Api\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;
use XF\PrintableException;

class ColumnOption extends AbstractOption
{
    public static function verifyOnOffOption(&$value, Option $option)
    {
        if (empty($value)) {
            // no verify when disable the option

            return true;
        }

        $subscriptionTopicType = preg_replace('/^.+subscription/', '', $option->option_id);
        switch ($subscriptionTopicType) {
            case 'User':
                $table = 'xf_user_option';
                break;
            case 'UserNotification':
                $table = 'xf_user_option';
                break;
            case 'ThreadPost':
                $table = 'xf_thread';
                break;
            default:
                throw new PrintableException(sprintf('Unsupported option %s', $subscriptionTopicType));
        }

        $column = \XF::app()
            ->options()
            ->offsetGet('bdApi_subscriptionColumn' . $subscriptionTopicType);

        if (!self::checkColumnExists($table, $column, $option)) {
            return false;
        }

        return true;
    }

    public static function verifyTextboxOption(&$value, Option $option)
    {
        $subscriptionTopicType = preg_replace('/^.+subscriptionColumn/', '', $option->option_id);
        switch ($subscriptionTopicType) {
            case 'User':
                $table = 'xf_user_option';
                break;
            case 'UserNotification':
                $table = 'xf_user_option';
                break;
            case 'ThreadPost':
                $table = 'xf_thread';
                break;
            default:
                throw new PrintableException(sprintf('Unsupported option %s', $subscriptionTopicType));
        }

        if (!empty($value) && !self::checkColumnExists($table, $value, $option)) {
            return false;
        }

        return true;
    }

    protected static function checkColumnExists($table, $column, Option $option)
    {
        $existed = \XF::db()->fetchOne(sprintf('SHOW COLUMNS FROM `%s` LIKE "%s"', $table, $column));
        if (!$existed) {
            $option->error(\XF::phrase('bdapi_column_x_table_y_not_found_for_field_z', [
                'column' => $column,
                'table' => $table,
                'field' => $option->option_id,
            ]));

            return false;
        }

        return true;
    }
}