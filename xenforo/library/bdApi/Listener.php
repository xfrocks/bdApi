<?php

class bdApi_Listener
{
    public static function load_class($class, array &$extend)
    {
        // these classes are always extended
        static $classes = array(
            'XenForo_ControllerPublic_Account',
            'XenForo_ControllerPublic_Error',
            'XenForo_ControllerPublic_Login',
            'XenForo_ControllerPublic_Logout',
            'XenForo_ControllerPublic_Misc',
            'XenForo_ControllerPublic_Register',

            'XenForo_DataWriter_Alert',
            'XenForo_DataWriter_Discussion_Thread',
            'XenForo_DataWriter_DiscussionMessage_Post',
            'XenForo_DataWriter_User',

            'XenForo_Model_Alert',
            'XenForo_Model_Conversation',
        );

        if (in_array($class, $classes)) {
            $extend[] = 'bdApi_' . $class;
        }
    }

    public static function extend($class, array &$extend)
    {
        // these classes are extended only within api context
        // this is done to reduce performance impact for public context
        static $classes = array(
            'XenForo_Model_Attachment',
            'XenForo_Model_Category',
            'XenForo_Model_Conversation',
            'XenForo_Model_Forum',
            'XenForo_Model_ForumWatch',
            'XenForo_Model_LinkForum',
            'XenForo_Model_Page',
            'XenForo_Model_Poll',
            'XenForo_Model_Post',
            'XenForo_Model_ProfilePost',
            'XenForo_Model_Search',
            'XenForo_Model_Tag',
            'XenForo_Model_Thread',
            'XenForo_Model_ThreadPrefix',
            'XenForo_Model_ThreadWatch',
            'XenForo_Model_User',
            'XenForo_Model_UserField',
            'XenForo_Model_UserGroup',
            'XenForo_Model_UserIgnore',

            'XenForo_Visitor',
        );

        if (in_array($class, $classes)) {
            $extend[] = str_replace('XenForo_', 'bdApi_Extend_', $class);
        }
    }

    public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        XenForo_CacheRebuilder_Abstract::$builders['bdApi_CacheRebuilder_ClientContentDeleteAll']
            = 'bdApi_CacheRebuilder_ClientContentDeleteAll';

        bdApi_ShippableHelper_Updater::onInitDependencies($dependencies);
    }

    public static function front_controller_pre_route(XenForo_FrontController $fc)
    {
        // use cookie flag to change web UI interface to match requested language_id from api
        $request = $fc->getRequest();
        $apiLanguageId = $request->getParam('_apiLanguageId');
        if (!empty($apiLanguageId)
            && preg_match('#^(?<timestamp>\d+) (?<data>.+)$#', $apiLanguageId, $matches)
        ) {
            try {
                $languageId = bdApi_Crypt::decryptTypeOne($matches['data'], $matches['timestamp']);
                if ($languageId > 0) {
                    $cookiePrefix = XenForo_Application::getConfig()->get('cookie')->get('prefix');
                    XenForo_Helper_Cookie::setCookie('language_id', $languageId);
                    $_COOKIE[$cookiePrefix . 'language_id'] = $languageId;
                    $fc->getResponse()->setHeader('X-Api-Language', $languageId);
                }
            } catch (XenForo_Exception $e) {
                // ignore
            }
        }
    }

    public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template)
    {
        static $initTemplateHelper = false;
        if ($initTemplateHelper === false) {
            $initTemplateHelper = true;
            bdApi_Template_Helper_Core::initTemplateHelpers();
        }

        if ($templateName == 'account_wrapper') {
            $template->preloadTemplate('bdapi_account_wrapper_sidebar');
        } elseif ($templateName == 'PAGE_CONTAINER') {
            $template->preloadTemplate('bdapi_navigation_visitor_tab');
        }
    }

    public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        switch ($hookName) {
            case 'account_wrapper_sidebar_settings':
                $ourTemplate = $template->create('bdapi_account_wrapper_sidebar', $template->getParams());
                $ourHtml = $ourTemplate->render();
                $contents .= $ourHtml;
                break;
            case 'navigation_visitor_tab_links1':
                $ourTemplate = $template->create('bdapi_navigation_visitor_tab', $template->getParams());
                $ourHtml = $ourTemplate->render();
                $contents .= $ourHtml;
                break;
        }
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $ourHashes = bdApi_FileSums::getHashes();
        $hashes += $ourHashes;
    }

}
