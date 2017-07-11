<?php

class bdApi_Data_ColumnOption
{
    public static function renderOnOff(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        list($table, $column) = self::_getTableAndColumnForOnOffOption($preparedOption['option_id']);
        $preparedOption['_bdApiTable'] = $table;
        $preparedOption['_bdApiColumn'] = $column;

        return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
            'bdapi_column_option_onoff',
            $view,
            $fieldPrefix,
            $preparedOption,
            $canEdit
        );
    }

    public static function verifyOnOff($value, XenForo_DataWriter $dw, $fieldName)
    {
        if (empty($value)) {
            return true;
        }

        list($table, $column) = self::_getTableAndColumnForOnOffOption($fieldName);
        return self::_verify($table, $column, $dw, $fieldName);
    }

    public static function verifyXfPost($column, XenForo_DataWriter $dw, $fieldName)
    {
        return self::_verify('xf_post', $column, $dw, $fieldName);
    }

    public static function updateIfExists($optionName, $table, $column)
    {
        $db = XenForo_Application::getDb();
        $existed = $db->fetchOne(sprintf('SHOW COLUMNS FROM `%s` LIKE "%s"', $table, $column));
        if ($existed) {
            /** @var XenForo_Model_Option $optionModel */
            $optionModel = XenForo_Model::create('XenForo_Model_Option');
            $optionModel->updateOption($optionName, $column);
        }
    }

    protected static function _verify($table, $column, XenForo_DataWriter $dw, $fieldName)
    {
        if (empty($column)) {
            return true;
        }

        $db = XenForo_Application::getDb();
        $existed = $db->fetchOne(sprintf('SHOW COLUMNS FROM `%s` LIKE "%s"', $table, $column));
        if (!$existed) {
            $dw->error(new XenForo_Phrase('bdapi_column_x_table_y_not_found_for_field_z', array(
                'column' => $column,
                'table' => $table,
                'field' => $fieldName,
            )));
            return false;
        }

        return true;
    }

    protected static function _getTableAndColumnForOnOffOption($fieldName)
    {
        $subscriptionTopicType = preg_replace('/^.+subscription/', '', $fieldName);
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
                throw new XenForo_Exception(sprintf('Unsupported option %s', $subscriptionTopicType));
        }

        $columnConfigKey = 'subscriptionColumn' . $subscriptionTopicType;
        $column = bdApi_Option::getConfig($columnConfigKey);

        return array($table, $column);
    }
}
