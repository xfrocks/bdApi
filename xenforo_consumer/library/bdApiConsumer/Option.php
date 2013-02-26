<?php
class bdApiConsumer_Option
{
	public static function get($key)
	{
		$options = XenForo_Application::getOptions();

		switch ($key)
		{
			case '_activated': return true;
			case '_producers': return array(
			array('code' => 'test', 'name' => 'Test', 'root' => 'http://localxf.daohoangson.com/api', 'client_id' => 2, 'client_secret' => 'secret'),
			);
		}

		return $options->get('bdapi_consumer_' . $key);
	}
	
	public static function getProducerByCode($code)
	{
		$producers = self::get('_producers');
		
		foreach ($producers as $producer)
		{
			if ($producer['code'] === $code)
			{
				return $producer;
			}
		}
		
		return false;
	}
}