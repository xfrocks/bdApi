<?php

namespace Xfrocks\Api\Util;

class Vendor
{
    public static function load()
    {
        require_once(dirname(__DIR__) . '/vendor/autoload.php');
    }
}
