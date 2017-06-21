<?php

class bdApi_Data_ColumnOption
{
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
}