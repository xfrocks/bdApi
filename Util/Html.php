<?php

namespace Xfrocks\Api\Util;

class Html
{
    /**
     * @param string $color
     * @return array
     */
    public static function parseColor($color)
    {
        if (preg_match('/^#(([0-9a-f]{3}){1,2})$/i', $color, $matches) !== 1) {
            return [0, 0, 0];
        }

        $code = $matches[1];
        $codeLength = strlen($code);
        $elementLength = intval($codeLength / 3);

        $elements = [];
        for ($i = 0; $i < 3; $i++) {
            $hex = substr($code, $i * $elementLength, $elementLength);
            $elements[$i] = hexdec($hex);
        }

        return $elements;
    }

    /**
     * @param string $fontFamily
     * @return string
     */
    public static function parseFontFamily($fontFamily)
    {
        $parts = explode(',', $fontFamily);
        $font = strval(reset($parts));
        $font = trim($font);

        if (preg_match('#^(\'|")(.+)\\1$#', $font, $matches) === 1) {
            $font = $matches[2];
        }

        return $font;
    }
}
