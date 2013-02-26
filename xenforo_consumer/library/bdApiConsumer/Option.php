<?php
class bdApiConsumer_Option
{
	public static function get($key)
	{
		$options = XenForo_Application::getOptions();

		switch ($key)
		{
			case '_activated': return true;
			case '_providers': return array(
			array('code' => 'test', 'name' => 'Test', 'root' => 'http://localxf.daohoangson.com/api', 'client_id' => 2, 'client_secret' => 'secret'),
			);
		}

		return $options->get('bdapi_consumer_' . $key);
	}
	
	public static function getProviderByCode($code)
	{
		$providers = self::get('_providers');
		
		foreach ($providers as $provider)
		{
			if ($provider['code'] === $code)
			{
				return $provider;
			}
		}
		
		return false;
	}
}