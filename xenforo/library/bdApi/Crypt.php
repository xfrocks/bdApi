<?php

class bdApi_Crypt
{
	const AES128 = 'aes128';
	
	public static function getDefaultAlgo()
	{
		return self::AES128;
	}

	public static function encrypt($data, $algo)
	{
		$key = self::_getGet($algo);

		switch ($algo)
		{
			case self::AES128:
				$encrypted = base64_encode(self::_aes128_encrypt($data, $key));
				break;
			default:
				$encrypted = $data;
		}

		return $encrypted;
	}

	public static function decrypt($data, $algo)
	{
		$key = self::_getGet($algo);
		
		switch ($algo)
		{
			case self::AES128:
				$decrypted = self::_aes128_decrypt(base64_decode($data), $key);
				break;
			default:
				$decrypted = $data;
		}

		return $decrypted;
	}
	
	protected static function _getGet($algo)
	{
		if (!empty($algo))
		{
			$session = XenForo_Application::getSession();
			$clientSecret = $session->getOAuthClientSecret();
			if (empty($clientSecret))
			{
				throw new XenForo_Exception(new XenForo_Phrase('bdapi_request_must_authorize_to_encrypt'), true);
			}
				
			return $clientSecret;
		}
		
		return false;
	}

	protected static function _aes128_encrypt($data, $key)
	{
		$key = md5($key, true);
		$padding = 16 - (strlen($data) % 16);
		$data .= str_repeat(chr($padding), $padding);
		return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
	}

	protected static function _aes128_decrypt($data, $key)
	{
		$key = md5($key, true);
		$data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
		$padding = ord($data[strlen($data) - 1]);
		return substr($data, 0, -$padding);
	}
}
