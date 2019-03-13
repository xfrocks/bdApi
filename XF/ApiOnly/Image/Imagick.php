<?php

namespace Xfrocks\Api\XF\ApiOnly\Image;

class Imagick extends XFCP_Imagick implements DriverInterface
{
    public function setBackgroundColorForApi($r, $g, $b)
    {
        $image = $this->imagick;

        $color = new \ImagickPixel(sprintf('rgb(%d, %d, %d)', $r, $g, $b));
        $x = $y = 0;
        $target = $image->getimagepixelcolor($x, $y);
        $image->floodfillpaintimage($color, 1, $target, $x, $y, false);
    }

    public function putTextAtCenterForApi($percent, $r, $g, $b, $fontFile, $text)
    {
        $image = $this->imagick;

        $draw = new \ImagickDraw();
        $draw->setfillcolor(new \ImagickPixel(sprintf('rgb(%d, %d, %d)', $r, $g, $b)));
        $draw->setfont($fontFile);
        $draw->setgravity(\Imagick::GRAVITY_CENTER);

        $size = ceil($this->height * $percent / 10) / 10;
        $checkedSizes = [];
        while (true) {
            $draw->setfontsize($size);
            $metrics = $image->queryfontmetrics($draw, $text);
            $checkedSizes[] = $size;

            $boxPercent = $metrics['textHeight'] / $this->height * 100;
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

        $image->annotateimage($draw, 0, 0, 0, $text);
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Imagick extends \XF\Image\Imagick
    {
        // extension hint
    }
}
