<?php

class bdApi_Template_Helper_Core
{
    public function buildLink()
    {
        $args = func_get_args();
        return call_user_func_array(array('bdApi_Data_Helper_Core', 'safeBuildApiLink'), $args);
    }

    public function scopeSplit($scopesStr)
    {
        return array_map('trim', preg_split('#\s#', $scopesStr, -1, PREG_SPLIT_NO_EMPTY));
    }

    public function scopeJoin(array $scopes)
    {
        return implode(' ', array_map('trim', $scopes));
    }

    public function scopeGetText($scope)
    {
        switch ($scope) {
            case bdApi_Model_OAuth2::SCOPE_READ:
                return new XenForo_Phrase('bdapi_scope_read');
            case bdApi_Model_OAuth2::SCOPE_POST:
                return new XenForo_Phrase('bdapi_scope_post');
            case bdApi_Model_OAuth2::SCOPE_MANAGE_ACCOUNT_SETTINGS:
                return new XenForo_Phrase('bdapi_scope_manage_account_settings');
            case bdApi_Model_OAuth2::SCOPE_PARTICIPATE_IN_CONVERSATIONS:
                return new XenForo_Phrase('bdapi_scope_participate_in_conversations');
            case bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM:
                return new XenForo_Phrase('bdapi_scope_manage_system');
        }

        return false;
    }

    public function visitorHasPermission($permission, $group = 'general')
    {
        return XenForo_Visitor::getInstance()->hasPermission($group, 'bdApi_' . $permission);
    }

    private function __construct()
    {
        // singleton
    }

    private function __clone()
    {
        // singleton
    }

    /**
     * Singleton instance
     * @var bdApi_Template_Helper_Core
     */
    private static $_instance = null;

    /**
     * @return bdApi_Template_Helper_Core
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            $templateHelperClass = XenForo_Application::resolveDynamicClass(__CLASS__);
            self::$_instance = new $templateHelperClass();
        }

        return self::$_instance;
    }

    public static function initTemplateHelpers()
    {
        $templateHelper = bdApi_Template_Helper_Core::getInstance();

        // register the helper methods in the format `api_<method_name>`
        $templateHelperReflector = new ReflectionClass(get_class($templateHelper));
        $methods = $templateHelperReflector->getMethods();
        foreach ($methods as $method) {
            if (!($method->getModifiers() & ReflectionMethod::IS_PUBLIC)
                || ($method->getModifiers() & ReflectionMethod::IS_STATIC)
            ) {
                // ignore restricted or static methods
                continue;
            }

            $methodName = $method->getName();
            $helperCallbackName = utf8_strtolower('api_' . $methodName);
            XenForo_Template_Helper_Core::$helperCallbacks[$helperCallbackName] = array(
                $templateHelper,
                $methodName
            );
        }
    }
}
