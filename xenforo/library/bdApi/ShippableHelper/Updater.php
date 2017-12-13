<?php

// updated by DevHelper_Helper_ShippableHelper at 2017-12-05T22:45:12+00:00

/**
 * Class bdApi_ShippableHelper_Updater
 * @version 8
 * @see DevHelper_Helper_ShippableHelper_Updater
 */
class bdApi_ShippableHelper_Updater
{
    const KEY = 'ShippableHelper_Updater';
    const API_URL = 'https://xfrocks.com/api/index.php?updater';

    public static $_version = 2016050601;

    /**
     * Verifies config to make sure the Updater should run.
     * 1. It has not been configured yet
     * 2. Or it has been enabled at some point
     *
     * Tips:
     * 1. Wrap this call within a `if (isset($data['routesAdmin'])) { ... }` for small performance boost
     *
     * @param XenForo_Dependencies_Abstract $dependencies
     * @param string|null $apiUrl
     * @param string|null $addOnId
     * @throws XenForo_Exception
     */
    public static function onInitDependencies(
        XenForo_Dependencies_Abstract $dependencies,
        $apiUrl = null,
        $addOnId = null
    ) {
        if (get_class($dependencies) !== 'XenForo_Dependencies_Admin') {
            // do nothing unless user are in AdminCP
            return;
        }

        if (XenForo_Application::$versionId < 1020000) {
            // only run if XenForo is at least 1.2.0
            return;
        }

        if (!is_string($apiUrl)) {
            // use default api url if nothing provided
            // this will also make this method compatible with XenForo code event
            // in which an array will be sent as the second parameter
            $apiUrl = self::API_URL;
        }

        if (!parse_url($apiUrl, PHP_URL_HOST)) {
            throw new XenForo_Exception(sprintf('$apiUrl is invalid: %s', $apiUrl));
        }

        $addOnId = self::_getAddOnId($addOnId);
        $config = self::_readConfig($addOnId, $apiUrl);
        if (!empty($config['configured'])
            && empty($config['enabled'])
        ) {
            // admin has turned it off, bye bye
            return;
        }

        if (!isset($GLOBALS[self::KEY])) {
            $GLOBALS[self::KEY] = array(
                'version' => array(),
                'onPreRoute' => array(),
                'setupMethod' => array(),
                'addOns' => array(),
            );
        }

        $GLOBALS[self::KEY]['version'][$apiUrl][self::$_version]
            = 'bdApi_ShippableHelper_UpdaterCore';

        if (!isset($GLOBALS[self::KEY]['onPreRoute'][$apiUrl])) {
            $GLOBALS[self::KEY]['onPreRoute'][$apiUrl] = function ($fc) use ($config) {
                self::onPreRoute($fc, $config);
            };
            XenForo_CodeEvent::addListener('front_controller_pre_route',
                $GLOBALS[self::KEY]['onPreRoute'][$apiUrl]);
        }

        $GLOBALS[self::KEY][$apiUrl]['addOns'][$addOnId] = true;
    }

    /**
     * Removes trace of Updater if it is no longer needed.
     *
     * @param string|null $apiUrl
     * @param string|null $addOnId
     * @throws Zend_Exception
     * @throws XenForo_Exception
     */
    public static function onUninstall($apiUrl = null, $addOnId = null)
    {
        if (XenForo_Application::$versionId < 1020000) {
            // only run if XenForo is at least 1.2.0
            return;
        }

        if (!is_string($apiUrl)) {
            // use default api url if nothing provided
            $apiUrl = self::API_URL;
        }

        $addOnId = self::_getAddOnId($addOnId);
        $config = self::_readConfig($addOnId, $apiUrl);
        $addOns = XenForo_Application::get('addOns');

        $activeAddOnIds = array();
        foreach ($config['addOnIds'] as $configAddOnId) {
            if ($configAddOnId === $addOnId
                || !isset($addOns[$configAddOnId])
            ) {
                continue;
            }

            $activeAddOnIds[] = $configAddOnId;
        }

        if (empty($activeAddOnIds)) {
            // no active add-ons found, trigger uninstall routine
            bdApi_ShippableHelper_UpdaterCore::_uninstallSelf($apiUrl);
        }
    }

    /**
     * Finds the latest Updater version and let it setup.
     * THIS METHOD SHOULD NOT BE ALTERED BETWEEN VERSIONS.
     *
     * @param XenForo_FrontController $fc
     * @param array $config
     *
     * @internal
     */
    public static function onPreRoute(
        /** @noinspection PhpUnusedParameterInspection */
        XenForo_FrontController $fc,
        array $config
    ) {
        if (empty($GLOBALS[self::KEY]['version'][$config['apiUrl']])) {
            return;
        }
        $versions = $GLOBALS[self::KEY]['version'][$config['apiUrl']];

        ksort($versions);
        $latest = array_pop($versions);

        call_user_func(array($latest, 'bootstrap'), $config);
    }

    public static function getConfigOptionId($apiUrl)
    {
        $configOptionId = sprintf('updater%s%s', str_replace(' ', '', ucwords(preg_replace('#[^A-Za-z]+#', ' ',
            parse_url($apiUrl, PHP_URL_HOST)))), md5($apiUrl));
        if (strlen($configOptionId) > 50) {
            $configOptionId = 'updater_' . md5($configOptionId);
        }

        return $configOptionId;
    }

    private static function _getAddOnId($addOnId)
    {
        if ($addOnId !== null) {
            return $addOnId;
        }

        $clazz = __CLASS__;
        $strPos = strpos($clazz, '_ShippableHelper_');
        if ($strPos === false) {
            throw new XenForo_Exception('Unable to determine $addOnId');
        }

        return substr($clazz, 0, $strPos);
    }

    private static function _readConfig($addOnId, $apiUrl)
    {
        $options = XenForo_Application::getOptions();

        $configOptionId = self::getConfigOptionId($apiUrl);
        $config = $options->get($configOptionId);

        if ($config === null) {
            $config = array(
                'version' => 0,
                'apiUrl' => $apiUrl,
                'addOnIds' => array($addOnId),

                'enabled' => false,
                'ignored' => array(),
            );
        }

        return $config;
    }
}