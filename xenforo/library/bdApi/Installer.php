<?php
class bdApi_Installer {
	/* Start auto-generated lines of code. Change made will be overwriten... */

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
			'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_client`'
		),
		'token' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_token` (
				`token_id` INT(10) UNSIGNED AUTO_INCREMENT
				,`client_id` VARCHAR(255) NOT NULL
				,`token_text` VARCHAR(255) NOT NULL
				,`expire_date` INT(10) UNSIGNED NOT NULL
				,`user_id` INT(10) UNSIGNED NOT NULL
				,`scope` TEXT NOT NULL
				, PRIMARY KEY (`token_id`)
				,UNIQUE INDEX `token_text` (`token_text`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_token`'
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
				, PRIMARY KEY (`auth_code_id`)
				,UNIQUE INDEX `auth_code_text` (`auth_code_text`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_auth_code`'
		),
		'refresh_token' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_refresh_token` (
				`refresh_token_id` INT(10) UNSIGNED AUTO_INCREMENT
				,`client_id` VARCHAR(255) NOT NULL
				,`refresh_token_text` VARCHAR(255) NOT NULL
				,`expire_date` INT(10) UNSIGNED NOT NULL
				,`user_id` INT(10) UNSIGNED NOT NULL
				,`scope` TEXT NOT NULL
				, PRIMARY KEY (`refresh_token_id`)
				,UNIQUE INDEX `refresh_token_text` (`refresh_token_text`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_refresh_token`'
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
				
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_log`'
		)
	);
	protected static $_patches = array(
		array(
			'table' => 'xf_bdapi_token',
			'field' => 'issue_date',
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_bdapi_token\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_bdapi_token` LIKE \'issue_date\'',
			'alterTableAddColumnQuery' => 'ALTER TABLE `xf_bdapi_token` ADD COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
			'alterTableDropColumnQuery' => 'ALTER TABLE `xf_bdapi_token` DROP COLUMN `issue_date`'
		),
		array(
			'table' => 'xf_bdapi_auth_code',
			'field' => 'issue_date',
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_bdapi_auth_code\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_bdapi_auth_code` LIKE \'issue_date\'',
			'alterTableAddColumnQuery' => 'ALTER TABLE `xf_bdapi_auth_code` ADD COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
			'alterTableDropColumnQuery' => 'ALTER TABLE `xf_bdapi_auth_code` DROP COLUMN `issue_date`'
		),
		array(
			'table' => 'xf_bdapi_refresh_token',
			'field' => 'issue_date',
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_bdapi_refresh_token\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_bdapi_refresh_token` LIKE \'issue_date\'',
			'alterTableAddColumnQuery' => 'ALTER TABLE `xf_bdapi_refresh_token` ADD COLUMN `issue_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'',
			'alterTableDropColumnQuery' => 'ALTER TABLE `xf_bdapi_refresh_token` DROP COLUMN `issue_date`'
		),
		array(
			'table' => 'xf_post',
			'field' => 'bdapi_origin',
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_post\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_post` LIKE \'bdapi_origin\'',
			'alterTableAddColumnQuery' => 'ALTER TABLE `xf_post` ADD COLUMN `bdapi_origin` VARCHAR(255) DEFAULT \'\'',
			'alterTableDropColumnQuery' => 'ALTER TABLE `xf_post` DROP COLUMN `bdapi_origin`'
		)
	);

	public static function install() {
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
		
		self::installCustomized();
	}
	
	public static function uninstall() {
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
	
	private static function installCustomized() {
		// customized install script goes here
	}
	
	private static function uninstallCustomized() {
		// customized uninstall script goes here
	}
	
}