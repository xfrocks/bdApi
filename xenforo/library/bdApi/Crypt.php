<?php

class bdApi_Crypt
{
    const AES128 = 'aes128';

    public static function getDefaultAlgo()
    {
        return self::AES128;
    }

    public static function encrypt($data, $algo, $key = false)
    {
        if ($key === false) {
            $key = self::_getKey();
        }

        switch ($algo) {
            case self::AES128:
                $encrypted = base64_encode(bdApi_ShippableHelper_Crypt::encrypt(
                    $data,
                    $key,
                    bdApi_ShippableHelper_Crypt::ALGO_AES_128
                ));
                break;
            default:
                $encrypted = $data;
        }

        return $encrypted;
    }

    public static function decrypt($data, $algo, $key = false)
    {
        if ($key === false) {
            $key = self::_getKey();
        }

        switch ($algo) {
            case self::AES128:
                $decrypted = bdApi_ShippableHelper_Crypt::decrypt(
                    base64_decode($data),
                    $key,
                    bdApi_ShippableHelper_Crypt::ALGO_AES_128
                );
                break;
            default:
                $decrypted = $data;
        }

        return $decrypted;
    }

    public static function encryptTypeOne($data, $timestamp)
    {
        $algo = self::getDefaultAlgo();
        $key = $timestamp . XenForo_Application::getConfig()->get('globalSalt');
        return self::encrypt($data, $algo, $key);
    }

    public static function decryptTypeOne($data, $timestamp)
    {
        if ($timestamp < XenForo_Application::$time) {
            throw new XenForo_Exception('$timestamp has expired', false);
        }

        $algo = self::getDefaultAlgo();
        $key = $timestamp . XenForo_Application::getConfig()->get('globalSalt');
        return self::decrypt($data, $algo, $key);
    }

    protected static function _getKey()
    {
        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();
        $clientSecret = $session->getOAuthClientSecret();
        if (empty($clientSecret)) {
            throw new XenForo_Exception(new XenForo_Phrase('bdapi_request_must_authorize_to_encrypt'), true);
        }

        return $clientSecret;
    }
}
