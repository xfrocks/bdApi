<?php

class bdApiConsumer_Listener
{
    protected static $_commonTemplatesPreloaded = false;

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

    public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        XenForo_Template_Helper_Core::$helperCallbacks['bdapiconsumer_getoption'] = array(
            'bdApiConsumer_Option',
            'get'
        );
        XenForo_Template_Helper_Core::$helperCallbacks['bdapiconsumer_getprovidersdkjs'] = array(
            'bdApiConsumer_Helper_Template',
            'getProviderSdkJs'
        );

        if (bdApiConsumer_Option::get('takeOver', 'register')) {
            $options = XenForo_Application::getOptions();
            $options->set('registrationSetup', 'enabled', 0);
            $options->set('bdapi_consumer_bypassRegistrationActive', 1);
        }

        if (bdApiConsumer_Option::get('takeOver', 'avatar')) {
            bdApiConsumer_Helper_Avatar::setupHelper();
        }

        bdApiConsumer_ShippableHelper_Updater::onInitDependencies($dependencies, bdApiConsumer_Option::UPDATER_URL);
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

    public static function controller_post_dispatch(XenForo_Controller $controller, $controllerResponse, $controllerName, $action)
    {
        if (bdApiConsumer_Option::get('autoLogin') AND $controllerResponse instanceof XenForo_ControllerResponse_Redirect) {
            bdApiConsumer_Helper_AutoLogin::updateResponseRedirect($controller, $controllerResponse);
        }
    }

    public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template)
    {
        if (empty(self::$_commonTemplatesPreloaded)) {
            $template->preloadTemplate('bdapi_consumer_providers');
            $template->preloadTemplate('bdapi_consumer_page_container_head');

            if (!bdApiConsumer_Option::get('_is120+')) {
                $template->preloadTemplate('bdapi_consumer_navigation_visitor_tab_links1');
            }

            self::$_commonTemplatesPreloaded = true;
        }

        if ($templateName === 'PAGE_CONTAINER' AND !bdApiConsumer_Option::get('_is120+')) {
            if (bdApiConsumer_Option::get('_showButtons')) {
                // setting $eAuth in hook position login_bar_eauth_set doens't work
                // so we have to do it here. Risk: won't work if the container template changes
                // this is bad but it only runs in XenForo 1.1.x
                $params['eAuth'] = 1;
            }
        }

        if ($templateName == 'account_wrapper' AND !bdApiConsumer_Option::get('_is120+')) {
            $template->preloadTemplate('bdapi_consumer_account_wrapper_sidebar_settings');
        }
    }

    public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        switch ($hookName) {
            case 'bdapi_consumer_providers':
                $params = array_merge($template->getParams(), $hookParams);
                $params['providers'] = bdApiConsumer_Option::getProviders();

                $loginFacebook = bdApiConsumer_Option::get('loginFacebook');
                if (isset($params['providers'][$loginFacebook])) {
                    $params['loginFacebookProvider'] = $params['providers'][$loginFacebook];
                }

                $loginTwitter = bdApiConsumer_Option::get('loginTwitter');
                if (isset($params['providers'][$loginTwitter])) {
                    $params['loginTwitterProvider'] = $params['providers'][$loginTwitter];
                }

                $loginGoogle = bdApiConsumer_Option::get('loginGoogle');
                if (isset($params['providers'][$loginGoogle])) {
                    $params['loginGoogleProvider'] = $params['providers'][$loginGoogle];
                }

                $ourTemplate = $template->create($hookName, $params);
                $contents = $ourTemplate->render();
                break;

            case 'page_container_head':
                $params = $template->getParams();
                $params['providers'] = bdApiConsumer_Option::getProviders();

                $ourTemplate = $template->create('bdapi_consumer_' . $hookName, $params);
                $contents .= $ourTemplate->render();
                break;
            case 'login_bar_eauth_items':
                if (!bdApiConsumer_Option::get('_is120+')) {
                    // XenForo 1.1.x compatibility
                    $params = array_merge($template->getParams(), $hookParams);
                    $params['providers'] = bdApiConsumer_Option::getProviders();
                    $params['from'] = 'login_bar';

                    $ourTemplate = $template->create('bdapi_consumer_providers', $params);
                    $contents = $ourTemplate->render();
                }
                break;
            case 'account_wrapper_sidebar_settings':
            case 'navigation_visitor_tab_links1':
                if (!bdApiConsumer_Option::get('_is120+')) {
                    // XenForo 1.1.x compatibility
                    $ourTemplate = $template->create('bdapi_consumer_' . $hookName, $template->getParams());
                    $contents .= $ourTemplate->render();
                }
                break;
        }
    }

    public static function template_post_render($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
    {
        switch ($templateName) {
            case 'login':
            case 'error_with_login':
                if (!bdApiConsumer_Option::get('_is120+')) {
                    // XenForo 1.1.x compatibility
                    $params = $template->getParams();
                    $params['providers'] = bdApiConsumer_Option::getProviders();
                    $params['from'] = 'login_form';

                    $ourTemplate = $template->create('bdapi_consumer_providers', $template->getParams());
                    $content .= $ourTemplate->render();
                }
                break;
        }
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += bdApiConsumer_FileSums::getHashes();
    }

}
