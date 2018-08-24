<?php

namespace Xfrocks\Api\XF\ApiOnly\Image;

interface DriverInterface
{
    public function setBackgroundColorForApi($r, $g, $b);

    public function putTextAtCenterForApi($percent, $r, $g, $b, $fontFile, $text);

    public function output($format = null, $quality = null);
}
