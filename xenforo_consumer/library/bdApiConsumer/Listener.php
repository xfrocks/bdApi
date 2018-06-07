<?php

class bdApiConsumer_Listener
{
    public static function load_class($class, array &$extend)
    {
        static $classes = array(
            'bdApi_Model_Subscription',

            'XenForo_ControllerPublic_Account',
            'XenForo_ControllerPublic_Login',
            'XenForo_ControllerPublic_Logout',
            'XenForo_ControllerPublic_Member',
            'XenForo_ControllerPublic_Misc',
            'XenForo_ControllerPublic_Register',
            'XenForo_Model_Alert',
            'XenForo_Model_Avatar',
            'XenForo_Model_User',
            'XenForo_Model_UserConfirmation',
            'XenForo_Model_UserExternal',
        );

        if (in_array($class, $classes)) {
            $extend[] = 'bdApiConsumer_' . $class;
        }
    }

    public static function init_dependencies(
        XenForo_Dependencies_Abstract $dependencies,
        /** @noinspection PhpUnusedParameterInspection */
        array $data
    ) {
        XenForo_Template_Helper_Core::$helperCallbacks[strtolower('bdApiConsumer_getOption')] = array(
            'bdApiConsumer_Option',
            'get'
        );

        if (bdApiConsumer_Option::get('takeOver', 'register')) {
            $options = XenForo_Application::getOptions();
            $options->set('registrationSetup', 'enabled', 0);
            $options->set('bdapi_consumer_bypassRegistrationActive', 1);
        }

        if (bdApiConsumer_Option::get('takeOver', 'avatar')) {
            bdApiConsumer_Helper_Avatar::setupHelper();
        }

        bdApiConsumer_ShippableHelper_Updater::onInitDependencies($dependencies);
    }

    public static function visitor_setup(XenForo_Visitor &$visitor)
    {
        if (bdApiConsumer_Option::get('takeOver', 'avatar')) {
            // disable user ability to change avatar completely
            $permissions = $visitor['permissions'];
            $permissions['avatar']['allowed'] = false;
            $visitor['permissions'] = $permissions;
        }
    }


    public static function controller_post_dispatch(
        XenForo_Controller $controller,
        $controllerResponse,
        $controllerName,
        $action
    ) {
        if (bdApiConsumer_Option::get('autoLogin') AND $controllerResponse instanceof XenForo_ControllerResponse_Redirect) {
            bdApiConsumer_Helper_AutoLogin::updateResponseRedirect($controller, $controllerResponse);
        }
    }

    public static function file_health_check(
        XenForo_ControllerAdmin_Abstract $controller,
        array &$hashes
    ) {
        $hashes += bdApiConsumer_FileSums::getHashes();
    }
}
