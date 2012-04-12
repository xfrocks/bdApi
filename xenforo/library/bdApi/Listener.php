<?php

class bdApi_Listener
{
	public static function load_class($class, array &$extend)
	{
		static $classes = array(
			'XenForo_Model_Node',
			'XenForo_Model_Post',
			'XenForo_Model_Thread',
			'XenForo_Model_User',
		);
		
		if (in_array($class, $classes))
		{
			$extend[] = 'bdApi_' . $class;
		}
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$ourHashes = bdApi_FileSums::getHashes();
		$hashes += $ourHashes;
	}
}