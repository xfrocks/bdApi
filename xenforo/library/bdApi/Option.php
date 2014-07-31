<?php

class bdApi_Option
{
	public static function get($key)
	{
		$options = XenForo_Application::getOptions();
		
		switch ($key)
		{
			case 'keyLength': return 10;
			case 'secretLength': return 15;
		}
		
		return $options->get('bdApi_' . $key);
	}
}