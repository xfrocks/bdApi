<?php

class bdApiConsumer_Helper_Provider
{
	public static function getAccountSecurityLink(array $provider)
	{
		if (empty($provider['links']['account/security']))
		{
			$provider['links']['account/security'] = bdApiConsumer_Helper_Api::getPublicLink($provider, 'account/security');

			if (!empty($provider['links']['account/security']))
			{
				self::_updateProvider($provider);
			}
		}

		return $provider['links']['account/security'];
	}

	protected static function _updateProvider(array $provider)
	{
		$providers = bdApiConsumer_Option::getProviders();
		$providers[$provider['code']] = $provider;

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Option');
		$dw->setExistingData('bdapi_consumer_providers');
		$dw->set('option_value', $providers);
		return $dw->save();
	}

}
