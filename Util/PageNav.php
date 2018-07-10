<?php

namespace Xfrocks\Api\Util;

use Xfrocks\Api\Data\Params;

class PageNav
{
    /**
     * @param array $data
     * @param Params $params
     * @param int $total
     * @param string $link
     * @param mixed $linkData
     * @param array $config
     * @return array|null
     */
    public static function addLinksToData(
        array &$data,
        Params $params,
        $total,
        $link,
        $linkData = null,
        array $config = []
    ) {
        $filteredLimit = $params->getFilteredLimit();
        if (empty($filteredLimit)) {
            return null;
        }

        $filteredPage = $params->getFilteredPage();
        if (empty($filteredPage)) {
            return null;
        }

        $limit = $filteredLimit['value'];
        if ($total <= $limit) {
            return null;
        }

        $keyLinks = self::mapKey($config, 'links');
        $keyNext = self::mapKey($config, 'next');
        $keyPages = self::mapKey($config, 'pages');
        $keyPrev = self::mapKey($config, 'prev');
        $pageNav = [];
        $pageNav[$keyPages] = ceil($total / $limit);
        $pageMax = $filteredPage['max'];
        if ($pageMax > 0) {
            $pageNav[$keyPages] = min($pageNav[$keyPages], $pageMax);
        }
        if ($pageNav[$keyPages] < 2) {
            return null;
        }

        $page = $filteredPage['value'];
        $keyPage = $filteredPage['key'];
        $pageNav[$keyPage] = max(1, min($page, $pageNav[$keyPages]));

        $linkParams = [];
        foreach ($params->getFilteredValues() as $linkParamKey => $linkParamValue) {
            $paramFiltered = $params->getFiltered($linkParamKey);
            if ($paramFiltered['valueRaw'] === $paramFiltered['default']) {
                continue;
            }
            if ($linkParamValue === $paramFiltered['default']) {
                continue;
            }

            $linkParams[$linkParamKey] = $linkParamValue;
        }
        ksort($linkParams);

        if ($pageNav[$keyPage] > 1) {
            $prevLinkParams = array_merge($linkParams, [$keyPage => $pageNav[$keyPage] - 1]);
            $pageNav[$keyPrev] = $params->getController()->buildApiLink($link, $linkData, $prevLinkParams);
        }

        if ($pageNav[$keyPage] < $pageNav['pages']) {
            $nextLinkParams = array_merge($linkParams, [$keyPage => $pageNav[$keyPage] + 1]);
            $pageNav[$keyNext] = $params->getController()->buildApiLink($link, $linkData, $nextLinkParams);
        }

        if (!isset($data[$keyLinks])) {
            $data[$keyLinks] = [];
        }
        $data[$keyLinks] = array_merge($data[$keyLinks], $pageNav);

        return $pageNav;
    }

    /**
     * @param array $config
     * @param string $key
     * @return string
     */
    protected static function mapKey(array $config, $key)
    {
        if (!empty($config['keys'][$key])) {
            return $config['keys'][$key];
        }
        return $key;
    }
}
