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
        }

        return $options->get('bdApi_' . $key);
    }

    public static function getSubscription($topicType)
    {
        $optionKey = str_replace(' ', '', ucwords(str_replace('_', ' ', $topicType)));
        return self::get('subscription' . $optionKey);
    }

}
