<?php

namespace Xfrocks\Api\XF\ApiOnly\Image;

class Imagick extends XFCP_Imagick implements DriverInterface
{
    public function setBackgroundColorForApi($r, $g, $b)
    {
        $image = $this->imagick;

        $color = new \ImagickPixel(sprintf('rgb(%d, %d, %d)', $r, $g, $b));
        $x = $y = 0;
        $target = $image->getImagePixelColor($x, $y);
        $image->floodFillPaintImage($color, 1, $target, $x, $y, false);
    }

    public function putTextAtCenterForApi($percent, $r, $g, $b, $fontFile, $text)
    {
        $image = $this->imagick;

        $draw = new \ImagickDraw();
        $draw->setFillColor(new \ImagickPixel(sprintf('rgb(%d, %d, %d)', $r, $g, $b)));
        $draw->setFont($fontFile);
        $draw->setGravity(\Imagick::GRAVITY_CENTER);

        $size = ceil($this->height * $percent / 10) / 10;
        $checkedSizes = [];
        while (true) {
            $draw->setFontSize($size);
            $metrics = $image->queryFontMetrics($draw, $text);
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

        $image->annotateImage($draw, 0, 0, 0, $text);
    }
}

if (false) {
    // @codingStandardsIgnoreLine
    class XFCP_Imagick extends \XF\Image\Imagick
    {
        // extension hint
    }
}
