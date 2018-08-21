<?php

namespace Xfrocks\Api\Util;

use XF\PrintableException;
use Xfrocks\Api\Entity\Token;

class Crypt
{
    const ALGO_AES_128 = 'aes128';
    const ALGO_AES_256 = 'aes256';

    const OPENSSL_METHOD_AES128 = 'aes-128-ecb';
    const OPENSSL_METHOD_AES256 = 'aes-256-cbc';
    const OPENSSL_OPT_RAW_DATA = 1;

    /**
     * @return string
     */
    public static function getDefaultAlgo()
    {
        return self::ALGO_AES_128;
    }

    /**
     * @param string $data
     * @param string $algo
     * @param string|false $key
     * @return string
     * @throws PrintableException
     */
    public static function encrypt($data, $algo, $key = false)
    {
        if ($key === false) {
            $key = self::getKey();
        }

        switch ($algo) {
            case self::ALGO_AES_128:
                $data = self::aes128Encrypt($data, $key);
                if (!$data) {
                    throw new \InvalidArgumentException('Cannot encrypt data');
                }

                $encrypted = base64_encode($data);
                break;
            case self::ALGO_AES_256:
                $data = self::aes256Encrypt($data, $key);
                if (!$data) {
                    throw new \InvalidArgumentException('Cannot encrypt data');
                }

                $encrypted = base64_encode($data);
                break;
            default:
                $encrypted = $data;
        }

        return $encrypted;
    }

    /**
     * @param string $data
     * @param string $algo
     * @param string|false $key
     * @return string|false
     * @throws PrintableException
     */
    public static function decrypt($data, $algo, $key = false)
    {
        if ($key === false) {
            $key = self::getKey();
        }

        switch ($algo) {
            case self::ALGO_AES_128:
                $decrypted = self::aes128Decrypt(strval(base64_decode($data)), $key);
                break;
            case self::ALGO_AES_256:
                $decrypted = self::aes256Decrypt(strval(base64_decode($data)), $key);
                break;
            default:
                $decrypted = $data;
        }

        return $decrypted;
    }

    /**
     * @param string $data
     * @param int $timestamp
     * @return string
     * @throws PrintableException
     */
    public static function encryptTypeOne($data, $timestamp)
    {
        $algo = self::getDefaultAlgo();
        $key = $timestamp . \XF::app()->config('globalSalt');
        return self::encrypt($data, $algo, $key);
    }

    /**
     * @param string $data
     * @param int $timestamp
     * @return string
     * @throws PrintableException
     */
    public static function decryptTypeOne($data, $timestamp)
    {
        if ($timestamp < \XF::$time) {
            throw new \InvalidArgumentException('$timestamp has expired');
        }

        $algo = self::getDefaultAlgo();
        $key = $timestamp . \XF::app()->config('globalSalt');
        $decrypted = self::decrypt($data, $algo, $key);

        if ($decrypted === false || $decrypted === '') {
            throw new \LogicException('$data could not be decrypted');
        }

        return $decrypted;
    }

    /**
     * @return string
     * @throws PrintableException
     */
    protected static function getKey()
    {
        /** @var mixed $session */
        $session = \XF::app()->session();
        $callable = [$session, 'getToken'];

        $clientSecret = null;
        if (is_callable($callable)) {
            /** @var Token|null $token */
            $token = call_user_func($callable);
            if ($token) {
                $clientSecret = $token->Client->client_secret;
            }
        }

        if (empty($clientSecret)) {
            throw new PrintableException(\XF::phrase('bdapi_request_must_authorize_to_encrypt'));
        }

        return $clientSecret;
    }

    /**
     * @param string $data
     * @param string $key
     * @return string|false
     */
    protected static function aes128Encrypt($data, $key)
    {
        $key = md5($key, true);
        return openssl_encrypt($data, self::OPENSSL_METHOD_AES128, $key, self::OPENSSL_OPT_RAW_DATA);
    }

    /**
     * @param string $data
     * @param string $key
     * @return string|false
     */
    protected static function aes128Decrypt($data, $key)
    {
        $key = md5($key, true);
        return openssl_decrypt($data, self::OPENSSL_METHOD_AES128, $key, self::OPENSSL_OPT_RAW_DATA);
    }

    /**
     * @param string $data
     * @param string $key
     * @return string
     */
    protected static function aes256Encrypt($data, $key)
    {
        $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD_AES256);
        if ($ivLength === false) {
            throw new \InvalidArgumentException('Cannot encrypt data');
        }

        $iv = openssl_random_pseudo_bytes($ivLength);
        if ($iv === false) {
            throw new \InvalidArgumentException('Cannot encrypt data');
        }

        $encrypted = openssl_encrypt($data, self::OPENSSL_METHOD_AES256, $key, self::OPENSSL_OPT_RAW_DATA, $iv);

        return self::ALGO_AES_256 . $iv . $encrypted;
    }

    /**
     * @param string $data
     * @param string $key
     * @return string|false
     */
    protected static function aes256Decrypt($data, $key)
    {
        /** @var int|false $prefixLength */
        $prefixLength = mb_strlen(self::ALGO_AES_256, '8bit');
        if ($prefixLength === false) {
            throw new \InvalidArgumentException('Cannot decrypt data');
        }

        $prefix = mb_substr($data, 0, $prefixLength);
        if ($prefix === self::ALGO_AES_256) {
            $ivLength = openssl_cipher_iv_length(self::OPENSSL_METHOD_AES256);
            if ($ivLength === false) {
                throw new \InvalidArgumentException('Cannot decrypt data');
            }

            $iv = mb_substr($data, $prefixLength, $ivLength, '8bit');
            $encrypted = mb_substr($data, $prefixLength + $ivLength, null, '8bit');

            return openssl_decrypt($encrypted, self::OPENSSL_METHOD_AES256, $key, self::OPENSSL_OPT_RAW_DATA, $iv);
        }

        return false;
    }
}
