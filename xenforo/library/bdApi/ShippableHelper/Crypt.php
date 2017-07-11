<?php

// updated by DevHelper_Helper_ShippableHelper at 2017-07-11T16:09:19+00:00

/**
 * Class bdApi_ShippableHelper_Crypt
 * @version 3
 * @see DevHelper_Helper_ShippableHelper_Crypt
 */
class bdApi_ShippableHelper_Crypt
{
    const ALGO_AES_128 = 'aes128';
    const ALGO_AES_256 = 'aes256';

    const OPENSSL_METHOD_AES128 = 'aes-128-ecb';
    const OPENSSL_METHOD_AES256 = 'aes-256-cbc';

    public static function encrypt($data, $key = null, $algo = null)
    {
        if ($key === null) {
            $key = XenForo_Application::getConfig()->get('globalSalt');
        }

        switch ($algo) {
            case self::ALGO_AES_128:
                return self::_aes128_encrypt($data, $key);
            default:
            case self::ALGO_AES_256:
                return self::_aes256_encrypt($data, $key);
        }
    }

    public static function decrypt($data, $key = null, $algo = null)
    {
        if ($key === null) {
            $key = XenForo_Application::getConfig()->get('globalSalt');
        }

        if ($algo === null) {
            if (substr($data, 0, strlen(self::ALGO_AES_256)) === self::ALGO_AES_256) {
                $algo = self::ALGO_AES_256;
            } else {
                $algo = self::ALGO_AES_128;
            }
        }

        switch ($algo) {
            case self::ALGO_AES_128:
                return self::_aes128_decrypt($data, $key);
            default:
            case self::ALGO_AES_256:
                return self::_aes256_decrypt($data, $key);
        }
    }

    /**
     * Legacy AES 128 encryption.
     * Supports both OpenSSL and mcrypt.
     * Warning: This method is insecure and potentially dangerous, should be avoided for new application.
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected static function _aes128_encrypt($data, $key)
    {
        if (function_exists('openssl_encrypt')) {
            $key = md5($key, true);
            return openssl_encrypt($data, self::OPENSSL_METHOD_AES128, $key, OPENSSL_RAW_DATA);
        }

        if (function_exists('mcrypt_encrypt')) {
            $key = md5($key, true);
            $padding = 16 - (strlen($data) % 16);
            $data .= str_repeat(chr($padding), $padding);
            /** @noinspection PhpDeprecationInspection */
            return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
        }

        return $data;
    }

    /**
     * Legacy AES 128 decryption.
     * Supports both OpenSSL and mcrypt.
     * Warning: This method is insecure and potentially dangerous, should be avoided for new application.
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected static function _aes128_decrypt($data, $key)
    {
        if (function_exists('openssl_decrypt')) {
            $key = md5($key, true);
            return openssl_decrypt($data, self::OPENSSL_METHOD_AES128, $key, OPENSSL_RAW_DATA);
        }

        if (function_exists('mcrypt_decrypt')) {
            $key = md5($key, true);
            /** @noinspection PhpDeprecationInspection */
            $data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
            $padding = ord($data[strlen($data) - 1]);
            return substr($data, 0, -$padding);
        }

        return $data;
    }

    /**
     * Secure AES 256 encryption.
     * This method only supports OpenSSL (unlike the AES 128 variant which also supports mcrypt).
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected static function _aes256_encrypt($data, $key)
    {
        if (function_exists('mb_substr') && function_exists('openssl_encrypt')) {
            $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD_AES256);
            $iv = openssl_random_pseudo_bytes($ivLength);
            $encrypted = openssl_encrypt($data, self::OPENSSL_METHOD_AES256, $key, OPENSSL_RAW_DATA, $iv);

            return self::ALGO_AES_256 . $iv . $encrypted;
        }

        return $data;
    }

    /**
     * Secure AES 256 decryption.
     * This method only supports OpenSSL (unlike the AES 128 variant which also supports mcrypt).
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected static function _aes256_decrypt($data, $key)
    {
        if (function_exists('mb_substr') && function_exists('openssl_encrypt')) {
            $prefixLength = mb_strlen(self::ALGO_AES_256, '8bit');
            $prefix = mb_substr($data, 0, $prefixLength);
            if ($prefix === self::ALGO_AES_256) {
                $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD_AES256);
                $iv = mb_substr($data, $prefixLength, $ivLength, '8bit');
                $encrypted = mb_substr($data, $prefixLength + $ivLength, null, '8bit');

                return openssl_decrypt($encrypted, self::OPENSSL_METHOD_AES256, $key, OPENSSL_RAW_DATA, $iv);
            }
        }

        return $data;
    }

}
