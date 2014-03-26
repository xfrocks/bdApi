<?php

class bdApiConsumer_Option
{
	protected static $_providers = null;
	protected static $_activated = null;
	protected static $_showButtons = null;

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
				if (self::$_activated === null)
				{
					$providers = self::getProviders();
					self::$_activated = !empty($providers);
				}
				return self::$_activated;
			case '_showButtons':
				if (self::$_showButtons === null)
				{
					self::$_showButtons = false;

					if (!self::get('takeOver', 'login'))
					{
						// no login take over, show the provider buttons
						self::$_showButtons = true;
					}

					if (self::get('loginFacebook') OR self::get('loginTwitter') OR self::get('loginGoogle'))
					{
						// show social buttons
						self::$_showButtons = true;
					}
				}
				return self::$_showButtons;
			case 'providers':
				if (self::$_providers === null)
				{
					self::$_providers = $options->get('bdapi_consumer_providers');
				}
				return self::$_providers;
		}

		return $options->get('bdapi_consumer_' . $key, $subKey);
	}

	public static function setProviders($providers)
	{
		self::$_providers = $providers;
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

	public static function renderOptionLoginSocial(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$providers = self::getProviders();

		switch ($preparedOption['option_id'])
		{
			case 'bdapi_consumer_loginFacebook':
				$social = 'facebook';
				break;
			case 'bdapi_consumer_loginTwitter':
				if (self::get('_is130+'))
				{
					$social = 'twitter';
				}
				break;
			case 'bdapi_consumer_loginGoogle':
				if (self::get('_is130+'))
				{
					$social = 'google';
				}
				break;
		}
		if (empty($social))
		{
			// unrecognized option
			return '';
		}

		$choices = array('' => '');
		foreach ($providers as $provider)
		{
			$providerLoginSocial = bdApiConsumer_Helper_Provider::getLoginSocial($provider);
			if (!empty($providerLoginSocial['social']) AND in_array($social, $providerLoginSocial['social'], true))
			{
				$choices[$provider['code']] = $provider['name'];
			}
		}
		$preparedOption['formatParams'] = $choices;

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal('option_list_option_select', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

}
