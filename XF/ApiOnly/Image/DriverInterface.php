<?php

namespace Xfrocks\Api\XF\ApiOnly\Image;

interface DriverInterface
{
    /**
     * @param int $r
     * @param int $g
     * @param int $b
     * @return void
     */
    public function setBackgroundColorForApi($r, $g, $b);

    /**
     * @param float $percent
     * @param int $r
     * @param int $g
     * @param int $b
     * @param string $fontFile
     * @param string $text
     * @return void
     */
    public function putTextAtCenterForApi($percent, $r, $g, $b, $fontFile, $text);

    /**
     * @param mixed|null $format
     * @param mixed|null $quality
     * @return string
     */
    public function output($format = null, $quality = null);
}
