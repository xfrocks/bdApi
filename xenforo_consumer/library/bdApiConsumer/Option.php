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
	
	public static function getProviders()
	{
		return self::get('providers');
	}
	
	public static function getProviderByCode($code)
	{
		$providers = self::getProviders();
		
		foreach ($providers as $provider)
		{
			if ($provider['code'] === $code)
			{
				return $provider;
			}
		}
		
		return false;
	}
	
	public static function renderOptionProviders(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$providers = self::getProviders();

		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject('bdapi_consumer_option_providers', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'formatParams' => $preparedOption['formatParams'],
			'editLink' => $editLink,

			'providers' => $providers
		));
	}
	
	public static function verifyOptionProviders(array &$providers, XenForo_DataWriter $dw, $fieldName)
	{
		$output = array();
		
		foreach ($providers as $provider)
		{
			if (!empty($provider['root']))
			{
				$provider['root'] = rtrim($provider['root'], '/');
			}
			
			if (!empty($provider['name']) AND !empty($provider['root']) AND !empty($provider['client_id']) AND !empty($provider['client_secret']))
			{
				$code = substr(md5($provider['root'] . $provider['client_id'] . $provider['client_secret']), -5);
				
				$output[$code] = array(
					'code' => $code,
					'name' => $provider['name'],
					'root' => $provider['root'],
					'client_id' => $provider['client_id'],
					'client_secret' => $provider['client_secret'],
				);
			}
		}
		
		$providers = $output;
		return true;
	}
}