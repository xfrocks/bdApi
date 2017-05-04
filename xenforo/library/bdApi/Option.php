<?php

class bdApi_Option
{
    public static function get($key)
    {
        $options = XenForo_Application::getOptions();

        switch ($key) {
            case 'keyLength':
                return 10;
            case 'secretLength':
                return 15;
            case 'authorizeBypassSecs':
                return 600;
        }

        return $options->get('bdApi_' . $key);
    }

    public static function getConfig($key)
    {
        static $config = null;

        if ($config === null) {
            $config = array(
                'pingQueueUseDefer' => true,
                'publicSessionToken' => 'public',
                'publicSessionClientId' => '',
                'publicSessionScopes' => 'read post',
            );

            $values = XenForo_Application::getConfig()->get('api');
            if ($values !== null && $values instanceof Zend_Config) {
                foreach (array_keys($config) as $_key) {
                    $value = $values->get($_key);
                    if ($value !== null) {
                        $config[$_key] = $value;
                    }
                }
            }
        }

        return isset($config[$key]) ? $config[$key] : null;
    }

    public static function getSubscription($topicType)
    {
        $optionKey = str_replace(' ', '', ucwords(str_replace('_', ' ', $topicType)));
        return self::get('subscription' . $optionKey);
    }

}
