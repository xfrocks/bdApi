<?php

namespace Xfrocks\Api\Option;

class ColumnOption extends \XF\Option\AbstractOption
{
    /**
     * @param int $value
     * @param \XF\Entity\Option $option
     * @return bool
     * @throws \XF\PrintableException
     */
    public static function verifyOnOffOption(&$value, $option)
    {
        if ($value === 0) {
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
                throw new \XF\PrintableException(sprintf('Unsupported option %s', $subscriptionTopicType));
        }

        $column = \XF::app()
            ->options()
            ->offsetGet('bdApi_subscriptionColumn' . $subscriptionTopicType);

        if (!self::checkColumnExists($table, $column, $option)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $value
     * @param \XF\Entity\Option $option
     * @return bool
     * @throws \XF\PrintableException
     */
    public static function verifyTextboxOption(&$value, $option)
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
                throw new \XF\PrintableException(sprintf('Unsupported option %s', $subscriptionTopicType));
        }

        if (strlen($value) > 0 && !self::checkColumnExists($table, $value, $option)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $table
     * @param string $column
     * @param \XF\Entity\Option $option
     * @return bool
     */
    protected static function checkColumnExists($table, $column, $option)
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
