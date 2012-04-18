<?php
class bdApi_Installer {
	/* Start auto-generated lines of code. Change made will be overwriten... */

	protected static $_tables = array(
		'client' => array(
			'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdapi_client` (
				`client_id` INT(10) UNSIGNED AUTO_INCREMENT
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
				,`client_id` INT(10) UNSIGNED NOT NULL
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
				,`client_id` INT(10) UNSIGNED NOT NULL
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
				,`client_id` INT(10) UNSIGNED NOT NULL
				,`refresh_token_text` VARCHAR(255) NOT NULL
				,`expire_date` INT(10) UNSIGNED NOT NULL
				,`user_id` INT(10) UNSIGNED NOT NULL
				,`scope` TEXT NOT NULL
				, PRIMARY KEY (`refresh_token_id`)
				,UNIQUE INDEX `refresh_token_text` (`refresh_token_text`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
			'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdapi_refresh_token`'
		)
	);
	protected static $_patches = array();

	public static function install() {
		$db = XenForo_Application::get('db');

		foreach (self::$_tables as $table) {
			$db->query($table['createQuery']);
		}
		
		foreach (self::$_patches as $patch) {
			$existed = $db->fetchOne($patch['showColumnsQuery']);
			if (empty($existed)) {
				$db->query($patch['alterTableAddColumnQuery']);
			}
		}
		
		self::installCustomized();
	}
	
	public static function uninstall() {
		$db = XenForo_Application::get('db');
		
		foreach (self::$_tables as $table) {
			$db->query($table['dropQuery']);
		}
		
		foreach (self::$_patches as $patch) {
			$existed = $db->fetchOne($patch['showColumnsQuery']);
			if (!empty($existed)) {
				$db->query($patch['alterTableDropColumnQuery']);
			}
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