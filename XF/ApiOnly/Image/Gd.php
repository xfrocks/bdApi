<?php

namespace Xfrocks\Api\XF\ApiOnly\Image;

class Gd extends XFCP_Gd implements DriverInterface
{
    public function setBackgroundColorForApi($r, $g, $b)
    {
        $color = imagecolorallocate($this->image, $r, $g, $b);
        imagefill($this->image, 0, 0, $color);
    }

    public function putTextAtCenterForApi($percent, $r, $g, $b, $fontFile, $text)
    {
        $color = imagecolorallocate($this->image, $r, $g, $b);

        $boxHeight = $boxWidth = 0;
        $size = ceil($this->height * $percent / 10) / 10;
        $checkedSizes = [];
        while (true) {
            $box = imagettfbbox($size, 0, $fontFile, $text);
            $checkedSizes[] = $size;
            $boxWidth = $box[2] - $box[0];
            $boxHeight = $box[1] - $box[7];

            $boxPercent = $boxHeight / $this->height * 100;
            $percentDelta = $boxPercent - $percent;
            if (abs($percentDelta) > 0.1) {
                if ($percentDelta > 0) {
                    $size -= 0.1;
                } else {
                    $size += 0.1;
                }
                if (in_array($size, $checkedSizes, true)) {
                    break;
                }
            } else {
                break;
            }
        }

        $x = intval(($this->width - $boxWidth) / 2);
        $y = intval(($this->height - $boxHeight) / 2 + $boxHeight);
        imagettftext($this->image, $size, 0, $x, $y, $color, $fontFile, $text);
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Gd extends \XF\Image\Gd
    {
        // extension hint
    }
}
