<?php

namespace Xfrocks\Api\Util;

use XF\PrintableException;

class Crypt
{
    const ALGO_AES_128 = 'aes128';
    const ALGO_AES_256 = 'aes256';

    const OPENSSL_METHOD_AES128 = 'aes-128-ecb';
    const OPENSSL_METHOD_AES256 = 'aes-256-cbc';
    const OPENSSL_OPT_RAW_DATA = 1;

    public static function getDefaultAlgo()
    {
        return self::ALGO_AES_128;
    }

    public static function encrypt($data, $algo, $key = false)
    {
        if ($key === false) {
            $key = self::getKey();
        }

        switch ($algo) {
            case self::ALGO_AES_128:
                $encrypted = base64_encode(self::aes128Encrypt($data, $key));
                break;
            case self::ALGO_AES_256:
                $encrypted = base64_encode(self::aes256Encrypt($data, $key));
                break;
            default:
                $encrypted = $data;
        }

        return $encrypted;
    }

    public static function decrypt($data, $algo, $key = false)
    {
        if ($key === false) {
            $key = self::getKey();
        }

        switch ($algo) {
            case self::ALGO_AES_128:
                $decrypted = self::aes128Decrypt(base64_decode($data), $key);
                break;
            case self::ALGO_AES_256:
                $decrypted = self::aes256Decrypt(base64_decode($data), $key);
                break;
            default:
                $decrypted = $data;
        }

        return $decrypted;
    }

    public static function encryptTypeOne($data, $timestamp)
    {
        $algo = self::getDefaultAlgo();
        $key = $timestamp . \XF::app()->config('globalSalt');
        return self::encrypt($data, $algo, $key);
    }

    public static function decryptTypeOne($data, $timestamp)
    {
        if ($timestamp < \XF::$time) {
            throw new \InvalidArgumentException('$timestamp has expired', false);
        }

        $algo = self::getDefaultAlgo();
        $key = $timestamp . \XF::app()->config('globalSalt');
        return self::decrypt($data, $algo, $key);
    }

    protected static function getKey()
    {
        /* @var $session \Xfrocks\Api\XF\Session\Session */
        $session = \XF::app()->session();
        $clientSecret = $session->getToken() ? $session->getToken()->Client->client_secret : null;
        if (empty($clientSecret)) {
            throw new PrintableException(\XF::phrase('bdapi_request_must_authorize_to_encrypt'));
        }

        return $clientSecret;
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
    protected static function aes128Encrypt($data, $key)
    {
        if (function_exists('openssl_encrypt')) {
            $key = md5($key, true);
            return openssl_encrypt($data, self::OPENSSL_METHOD_AES128, $key, self::OPENSSL_OPT_RAW_DATA);
        }

        if (function_exists('mcrypt_encrypt')) {
            $key = md5($key, true);
            $padding = 16 - (strlen($data) % 16);
            $data .= str_repeat(chr($padding), $padding);
            /** @noinspection PhpDeprecationInspection */
            return mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
        }

        throw new \RuntimeException('Cannot encrypt data');
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
    protected static function aes128Decrypt($data, $key)
    {
        if (function_exists('openssl_decrypt')) {
            $key = md5($key, true);
            return openssl_decrypt($data, self::OPENSSL_METHOD_AES128, $key, self::OPENSSL_OPT_RAW_DATA);
        }

        if (function_exists('mcrypt_decrypt')) {
            $key = md5($key, true);
            /** @noinspection PhpDeprecationInspection */
            $data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB);
            $padding = ord($data[strlen($data) - 1]);
            return substr($data, 0, -$padding);
        }

        throw new \RuntimeException('Cannot decrypt data');
    }

    /**
     * Secure AES 256 encryption.
     * This method only supports OpenSSL (unlike the AES 128 variant which also supports mcrypt).
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected static function aes256Encrypt($data, $key)
    {
        if (function_exists('mb_substr') && function_exists('openssl_encrypt')) {
            $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD_AES256);
            $iv = openssl_random_pseudo_bytes($ivLength);
            $encrypted = openssl_encrypt($data, self::OPENSSL_METHOD_AES256, $key, self::OPENSSL_OPT_RAW_DATA, $iv);

            return self::ALGO_AES_256 . $iv . $encrypted;
        }

        throw new \RuntimeException('Cannot encrypt data');
    }

    /**
     * Secure AES 256 decryption.
     * This method only supports OpenSSL (unlike the AES 128 variant which also supports mcrypt).
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected static function aes256Decrypt($data, $key)
    {
        if (function_exists('mb_substr') && function_exists('openssl_encrypt')) {
            $prefixLength = mb_strlen(self::ALGO_AES_256, '8bit');
            $prefix = mb_substr($data, 0, $prefixLength);
            if ($prefix === self::ALGO_AES_256) {
                $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD_AES256);
                $iv = mb_substr($data, $prefixLength, $ivLength, '8bit');
                $encrypted = mb_substr($data, $prefixLength + $ivLength, null, '8bit');

                return openssl_decrypt($encrypted, self::OPENSSL_METHOD_AES256, $key, self::OPENSSL_OPT_RAW_DATA, $iv);
            }
        }

        throw new \RuntimeException('Cannot decrypt data');
    }
}
