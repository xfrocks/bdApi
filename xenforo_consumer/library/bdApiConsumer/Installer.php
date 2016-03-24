<?php

class bdApiConsumer_Installer
{

    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected static $_tables = array();
    protected static $_patches = array(
        array(
            'table' => 'xf_user_profile',
            'field' => 'bdapiconsumer_unused',
            'showTablesQuery' => 'SHOW TABLES LIKE \'xf_user_profile\'',
            'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_user_profile` LIKE \'bdapiconsumer_unused\'',
            'alterTableAddColumnQuery' => 'ALTER TABLE `xf_user_profile` ADD COLUMN `bdapiconsumer_unused` VARCHAR(255)',
            'alterTableDropColumnQuery' => 'ALTER TABLE `xf_user_profile` DROP COLUMN `bdapiconsumer_unused`',
        ),
    );

    public static function install($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_tables as $table) {
            $db->query($table['createQuery']);
        }

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['showTablesQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['showColumnsQuery']);
            if (empty($existed)) {
                $db->query($patch['alterTableAddColumnQuery']);
            }
        }

        self::installCustomized($existingAddOn, $addOnData);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['showTablesQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['showColumnsQuery']);
            if (!empty($existed)) {
                $db->query($patch['alterTableDropColumnQuery']);
            }
        }

        foreach (self::$_tables as $table) {
            $db->query($table['dropQuery']);
        }

        self::uninstallCustomized();
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    private static function installCustomized(
        /** @noinspection PhpUnusedParameterInspection */
        $existingAddOn,
        $addOnData
    ) {
        if (XenForo_Application::$versionId < 1030000) {
            throw new XenForo_Exception('XenForo 1.3.0+ is required.');
        }

        $db = XenForo_Application::getDb();

        $db->query("REPLACE INTO `xf_content_type` (content_type, addon_id, fields) VALUES ('bdapi_consumer', 'bdApiConsumer', '')");
        $db->query("REPLACE INTO `xf_content_type_field` (content_type, field_name, field_value) VALUES ('bdapi_consumer', 'alert_handler_class', 'bdApiConsumer_AlertHandler_Provider')");
        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();
    }

    private static function uninstallCustomized()
    {
        $db = XenForo_Application::getDb();

        $db->query("DELETE FROM `xf_content_type` WHERE addon_id = ?", array('bdApiConsumer'));
        $db->query("DELETE FROM `xf_content_type_field` WHERE content_type = ?", array('bdapi_consumer'));
        $db->query("DELETE FROM `xf_user_alert` WHERE content_type = ?", array('bdapi_consumer'));
        $db->query("DELETE FROM `xf_user_alert_optout` WHERE alert LIKE 'bdapiconsumer_%s'");
        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();

        bdApiConsumer_ShippableHelper_Updater::onUninstall(bdApiConsumer_Option::UPDATER_URL);
    }

}
