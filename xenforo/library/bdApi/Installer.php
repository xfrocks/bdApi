<?php

class bdApi_Installer
{
    /* Start auto-generated lines of code. Change made will be overwritten... */

    protected static $_tables = array(
        'client' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_client` (
                `client_id` VARCHAR(255) NOT NULL
                ,`client_secret` VARCHAR(255) NOT NULL
                ,`redirect_uri` TEXT NOT NULL
                ,`name` VARCHAR(255) NOT NULL
                ,`description` TEXT NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`options` MEDIUMBLOB
                , PRIMARY KEY (`client_id`)
                
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_client`',
        ),
        'token' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_token` (
                `token_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`client_id` VARCHAR(255) NOT NULL
                ,`token_text` VARCHAR(255) NOT NULL
                ,`expire_date` INT(10) UNSIGNED NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`scope` TEXT NOT NULL
                ,`issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                , PRIMARY KEY (`token_id`)
                ,UNIQUE INDEX `token_text` (`token_text`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_token`',
        ),
        'auth_code' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_auth_code` (
                `auth_code_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`client_id` VARCHAR(255) NOT NULL
                ,`auth_code_text` VARCHAR(255) NOT NULL
                ,`redirect_uri` TEXT NOT NULL
                ,`expire_date` INT(10) UNSIGNED NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`scope` TEXT NOT NULL
                ,`issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                , PRIMARY KEY (`auth_code_id`)
                ,UNIQUE INDEX `auth_code_text` (`auth_code_text`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_auth_code`',
        ),
        'refresh_token' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_refresh_token` (
                `refresh_token_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`client_id` VARCHAR(255) NOT NULL
                ,`refresh_token_text` VARCHAR(255) NOT NULL
                ,`expire_date` INT(10) UNSIGNED NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`scope` TEXT NOT NULL
                ,`issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                , PRIMARY KEY (`refresh_token_id`)
                ,UNIQUE INDEX `refresh_token_text` (`refresh_token_text`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_refresh_token`',
        ),
        'log' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_log` (
                `log_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`client_id` VARCHAR(255) NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`ip_address` VARCHAR(50) NOT NULL
                ,`request_date` INT(10) UNSIGNED NOT NULL
                ,`request_method` VARCHAR(10) NOT NULL
                ,`request_uri` TEXT
                ,`request_data` MEDIUMBLOB
                ,`response_code` INT(10) UNSIGNED NOT NULL
                ,`response_output` MEDIUMBLOB
                , PRIMARY KEY (`log_id`)
                ,INDEX `request_date` (`request_date`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_log`',
        ),
        'subscription' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_subscription` (
                `subscription_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`client_id` VARCHAR(255) NOT NULL
                ,`callback` TEXT
                ,`topic` VARCHAR(255) NOT NULL
                ,`subscribe_date` INT(10) UNSIGNED NOT NULL
                ,`expire_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                , PRIMARY KEY (`subscription_id`)
                ,INDEX `client_id` (`client_id`)
                ,INDEX `topic` (`topic`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_subscription`',
        ),
        'user_scope' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_user_scope` (
                `client_id` VARCHAR(255) NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`scope` VARCHAR(255) NOT NULL
                ,`accept_date` INT(10) UNSIGNED NOT NULL
                
                ,INDEX `user_id` (`user_id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_user_scope`',
        ),
        'client_content' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_client_content` (
                `client_content_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`client_id` VARCHAR(255) NOT NULL
                ,`content_type` VARCHAR(25) NOT NULL
                ,`content_id` INT(10) UNSIGNED NOT NULL
                ,`title` VARCHAR(255) NOT NULL
                ,`body` TEXT NOT NULL
                ,`date` INT(10) UNSIGNED NOT NULL
                ,`link` TEXT NOT NULL
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`extra_data` MEDIUMBLOB
                , PRIMARY KEY (`client_content_id`)
                ,INDEX `content_type_content_id` (`content_type`,`content_id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_client_content`',
        ),
    );
    protected static $_patches = array(
        array(
            'table' => 'xf_bdapi_token',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_bdapi_token\'',
            'field' => 'issue_date',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_bdapi_token` LIKE \'issue_date\'',
            'addQuery' => 'ALTER TABLE `xf_bdapi_token` ADD COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'modifyQuery' => 'ALTER TABLE `xf_bdapi_token` MODIFY COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'dropQuery' => 'ALTER TABLE `xf_bdapi_token` DROP COLUMN `issue_date`',
        ),
        array(
            'table' => 'xf_bdapi_auth_code',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_bdapi_auth_code\'',
            'field' => 'issue_date',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_bdapi_auth_code` LIKE \'issue_date\'',
            'addQuery' => 'ALTER TABLE `xf_bdapi_auth_code` ADD COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'modifyQuery' => 'ALTER TABLE `xf_bdapi_auth_code` MODIFY COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'dropQuery' => 'ALTER TABLE `xf_bdapi_auth_code` DROP COLUMN `issue_date`',
        ),
        array(
            'table' => 'xf_bdapi_refresh_token',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_bdapi_refresh_token\'',
            'field' => 'issue_date',
            'checkQuery' => 'SHOW COLUMNS FROM `xf_bdapi_refresh_token` LIKE \'issue_date\'',
            'addQuery' => 'ALTER TABLE `xf_bdapi_refresh_token` ADD COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'modifyQuery' => 'ALTER TABLE `xf_bdapi_refresh_token` MODIFY COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
            'dropQuery' => 'ALTER TABLE `xf_bdapi_refresh_token` DROP COLUMN `issue_date`',
        ),
        array(
            'table' => 'xf_bdapi_log',
            'tableCheckQuery' => 'SHOW TABLES LIKE \'xf_bdapi_log\'',
            'index' => 'request_date',
            'checkQuery' => 'SHOW INDEXES FROM `xf_bdapi_log` WHERE Key_name LIKE \'request_date\'',
            'addQuery' => 'ALTER TABLE `xf_bdapi_log` ADD INDEX `request_date` (`request_date`)',
            'dropQuery' => 'ALTER TABLE `xf_bdapi_log` DROP INDEX `request_date`',
        ),
    );

    public static function install($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_tables as $table) {
            $db->query($table['createQuery']);
        }

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (empty($existed)) {
                $db->query($patch['addQuery']);
            } elseif (!empty($patch['modifyQuery'])) {
                $db->query($patch['modifyQuery']);
            }
        }

        self::installCustomized($existingAddOn, $addOnData);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (!empty($existed)) {
                $db->query($patch['dropQuery']);
            }
        }

        foreach (self::$_tables as $table) {
            $db->query($table['dropQuery']);
        }

        self::uninstallCustomized();
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    public static function installCustomized($existingAddOn, $addOnData)
    {
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
            throw new XenForo_Exception('PHP 5.4+ is required.');
        }

        if (!function_exists('openssl_encrypt')) {
            throw new XenForo_Exception('PHP OpenSSL support is required but could not be found.');
        }

        $db = XenForo_Application::getDb();

        $db->query('
            CREATE TABLE IF NOT EXISTS `xf_bdapi_ping_queue` (
                `ping_queue_id` INT(10) UNSIGNED AUTO_INCREMENT,
                `callback_md5` VARCHAR(32),
                `callback` TEXT,
                `object_type` VARBINARY(25) NOT NULL,
                `data` MEDIUMBLOB,
                `queue_date` INT(10) UNSIGNED NOT NULL,
                `expire_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\',
                PRIMARY KEY (`ping_queue_id`),
                INDEX `callback_md5` (`callback_md5`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
        ');

        $db->query(
            'REPLACE INTO `xf_content_type` (content_type, addon_id, fields) VALUES (?,?,?)',
            array('api_ping', 'bdApi', '')
        );
        $db->query(
            'REPLACE INTO `xf_content_type_field` (content_type, field_name, field_value) VALUES (?,?,?)',
            array('api_ping', 'alert_handler_class', 'bdApi_AlertHandler_Ping')
        );
        $db->query(
            'REPLACE INTO `xf_content_type` (content_type, addon_id, fields) VALUES (?,?,?)',
            array('api_client_content', 'bdApi', '')
        );
        $db->query(
            'REPLACE INTO `xf_content_type_field` (content_type, field_name, field_value) VALUES (?,?,?)',
            array('api_client_content', 'search_handler_class', 'bdApi_Search_DataHandler_ClientContent')
        );
        /** @var XenForo_Model_ContentType $contentTypeModel */
        $contentTypeModel = XenForo_Model::create('XenForo_Model_ContentType');
        $contentTypeModel->rebuildContentTypeCache();

        $existingVersionId = 0;
        if (!empty($existingAddOn)) {
            $existingVersionId = $existingAddOn['version_id'];
        }

        if ($existingVersionId < 1) {
            $db->query("
                INSERT IGNORE INTO xf_permission_entry
                (user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
                SELECT user_group_id, user_id, 'general', 'bdApi_clientNew', permission_value, 0
                FROM xf_permission_entry
                WHERE permission_group_id = 'general' AND permission_id = 'bypassFloodCheck'
            ");

            self::_installPhpDemoClient();
        }

        if ($existingVersionId < 1060001) {
            if (XenForo_Application::$versionId >= 1020000) {
                XenForo_Application::defer('bdApi_Deferred_Upgrade1060001', array());
            }
        }
    }

    public static function uninstallCustomized()
    {
        $db = XenForo_Application::getDb();

        $db->query('DROP TABLE IF EXISTS `xf_bdapi_ping_queue`');
        $db->query('DELETE FROM `xf_content_type` WHERE addon_id = ?', "bdApi");
        $db->query('DELETE FROM `xf_content_type_field` WHERE content_type = ?', "api_ping");
        $db->query('DELETE FROM `xf_content_type_field` WHERE content_type = ?', "api_client_content");

        bdApi_ShippableHelper_Updater::onUninstall();
    }

    protected static function _installPhpDemoClient()
    {
        /** @var bdApi_Model_Client $clientModel */
        $clientModel = XenForo_Model::create('bdApi_Model_Client');

        $siteUrl = 'http://api-php-demo.xfrocks.com/';

        /** @var bdApi_DataWriter_Client $dw */
        $dw = XenForo_DataWriter::create('bdApi_DataWriter_Client');
        $dw->bulkSet(array(
            'name' => 'Demo Site',
            'description' => 'Demo website for [bd] API setup and maintained by xfrocks.',
            'redirect_uri' => $siteUrl,
            'client_id' => $clientModel->generateClientId(),
            'client_secret' => $clientModel->generateClientSecret(),
            'user_id' => XenForo_Visitor::getUserId(),
            'options' => array(),
        ));
        $dw->save();

        $client = XenForo_Helper_Http::getClient($siteUrl . 'setup.php', array(
            'maxredirects' => 0,
        ));
        $client->setParameterGet('api_root', rtrim(XenForo_Application::getOptions()->get('boardUrl'), '/') . '/api');
        $client->setParameterGet('api_key', $dw->get('client_id'));
        $client->setParameterGet('api_secret', $dw->get('client_secret'));
        $client->setParameterGet('api_scope', 'read');

        try {
            $response = $client->request('HEAD');
            if ($response->getStatus() === 302) {
                $location = $response->getHeader('Location');
                if (!empty($location) && parse_url($location, PHP_URL_HOST) === 'j.mp') {
                    /** @var bdApi_DataWriter_Client $dw2 */
                    $dw2 = XenForo_DataWriter::create('bdApi_DataWriter_Client');
                    $dw2->setExistingData($dw->getMergedData());
                    $dw2->set('name', $location);
                    $dw2->save();
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }
}
