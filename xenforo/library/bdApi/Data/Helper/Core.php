<?php

class bdApi_Data_Helper_Core
{
    /**
     * Adds system information into response data (both XML and JSON)
     *
     * @param array $data
     */
    public static function addDefaultResponse(array &$data)
    {
        if (XenForo_Application::debugMode()) {
            $data['debug'] = XenForo_Debug::getDebugTemplateParams();

            $session = self::safeGetSession();
            if (!empty($session)) {
                $data['debug']['client_id'] = $session->getOAuthClientId();
                $data['debug']['oauth_token'] = $session->getOAuthTokenText();
            }
        }

        if (empty($data['system_info'])) {
            $data['system_info'] = array();
        }

        $data['system_info'] += array(
            'visitor_id' => XenForo_Visitor::getUserId(),
            'time' => XenForo_Application::$time,
        );
    }

    /**
     * Builds and adds the navigation for api data
     *
     * @param XenForo_Input $input
     * @param array $data
     * @param int $perPage
     * @param int $totalItems
     * @param int $page
     * @param string $linkType
     * @param mixed $linkData
     * @param array $linkParams
     * @param array $options
     */
    public static function addPageLinks(XenForo_Input $input, array &$data, $perPage, $totalItems, $page, $linkType, $linkData = null, array $linkParams = array(), array $options = array())
    {
        if (empty($perPage)) {
            return;
        }

        $pageNav = array();

        $inputData = $input->filter(array(
            'fields_include' => XenForo_Input::STRING,
            'fields_exclude' => XenForo_Input::STRING,
        ));
        if (!empty($inputData['fields_include'])) {
            $linkParams['fields_include'] = $inputData['fields_include'];
        } elseif (!empty($inputData['fields_exclude'])) {
            $linkParams['fields_exclude'] = $inputData['fields_exclude'];
        }

        if (empty($page)) {
            $page = 1;
        }

        $pageNav['pages'] = ceil($totalItems / $perPage);

        if ($pageNav['pages'] <= 1) {
            // do not do anything if there is only 1 page (or no pages)
            return;
        }

        $pageNav['page'] = $page;

        if ($page > 1) {
            // a previous link should only be added if we are not at page 1
            $pageNav['prev'] = XenForo_Link::buildApiLink($linkType, $linkData, array_merge($linkParams, array('page' => $page - 1)));
        }

        if ($page < $pageNav['pages']) {
            // a next link should only be added if we are not at the last page
            $pageNav['next'] = XenForo_Link::buildApiLink($linkType, $linkData, array_merge($linkParams, array('page' => $page + 1)));
        }

        // add the page navigation into `links`
        // the data may have existing links or not
        // we simply don't care
        if (empty($data['links'])) {
            $data['links'] = array();
        }
        $data['links'] = array_merge($data['links'], $pageNav);
    }

    /**
     * Filters data into another array with value from specified keys only
     *
     * @param array $data
     * @param array $publicKeys
     * @return array
     */
    public static function filter(array $data, array $publicKeys)
    {
        $filteredData = array();

        foreach ($publicKeys as $publicKey => $mappedKey) {
            if (is_int($publicKey)) {
                // backward compatible with previous versions
                // where $publicKeys is just an array of keys
                // (with no key value pair)
                $publicKey = $mappedKey;
            }

            if (isset($data[$publicKey])) {
                $filteredData[$mappedKey] = $data[$publicKey];
            }
        }

        return $filteredData;
    }

    /**
     * @return bdApi_Session|null
     */
    public static function safeGetSession()
    {
        if (XenForo_Application::isRegistered('_bdApi_session')) {
            return XenForo_Application::get('_bdApi_session');
        }

        if (XenForo_Application::isRegistered('session')) {
            $session = XenForo_Application::getSession();
            if ($session instanceof bdApi_Session) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @return string
     */
    public static function safeBuildApiLink()
    {
        $args = func_get_args();
        $func = array('XenForo_Link', 'buildApiLink');

        if (is_callable($func)) {
            return call_user_func_array($func, $args);
        } else {
            return '';
        }
    }
}
