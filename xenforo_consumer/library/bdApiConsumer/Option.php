<?php
class bdApiConsumer_Option
{
	public static function get($key, $subKey = null)
	{
		$options = XenForo_Application::getOptions();

		switch ($key)
		{
			case '_is120+':
				return XenForo_Application::$versionId >= 1020000;
			case '_is130+':
				return XenForo_Application::$versionId >= 1030000;
			case '_activated':
				$providers = self::getProviders();
				return !empty($providers);
		}

		return $options->get('bdapi_consumer_' . $key, $subKey);
	}

	public static function getProviders()
	{
		return self::get('providers');
	}

	public static function getProviderByCode($code)
	{
		$providers = self::getProviders();

		if (strpos($code, 'bdapi_') === 0)
		{
			$code = substr($code, 6);
		}

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

				$output[$code] = array_merge($provider, array(
					'code' => $code,
					'verified' => XenForo_Application::$time,
				));
			}
		}

		$providers = $output;
		return true;
	}

}
