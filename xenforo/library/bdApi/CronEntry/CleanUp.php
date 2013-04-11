<?php
class bdApi_CronEntry_CleanUp
{
	public static function pruneExpired()
	{
		XenForo_Model::create('bdApi_Model_AuthCode')->pruneExpired();
		XenForo_Model::create('bdApi_Model_RefreshToken')->pruneExpired();
		XenForo_Model::create('bdApi_Model_Token')->pruneExpired();
	}
}