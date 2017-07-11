<?php

class bdApi_ViewApi_Helper_Alert
{
    public static function getTemplates(XenForo_View $view, array $alerts, array $handlers)
    {
        $alerts = XenForo_ViewPublic_Helper_Alert::getTemplates($view, $alerts, $handlers);

        foreach ($alerts as $id => $item) {
            $alerts[$id]['template'] = self::convertUrisToAbsoluteUris($alerts[$id]['template']);
        }

        return $alerts;
    }

    public static function convertUrisToAbsoluteUris($html)
    {
        $offset = 0;
        while (true) {
            if (preg_match('#<a[^>]+href="([^"]+)"#', $html, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                $href = $matches[1];

                $uri = $href[0];
                $absoluteUri = XenForo_Link::convertUriToAbsoluteUri($uri, true);

                if ($uri !== $absoluteUri) {
                    $html = substr_replace($html, $absoluteUri, $href[1], strlen($uri));
                    $offset = $href[1] + strlen($absoluteUri);
                } else {
                    $offset = $href[1] + strlen($uri);
                }
            } else {
                break;
            }
        }

        return $html;
    }
}
