<?php

class bdApi_Listener
{
	public static function load_class($class, array &$extend)
	{
		static $classes = array(
				'XenForo_ControllerPublic_Account',
				'XenForo_ControllerPublic_Error',
				'XenForo_ControllerPublic_Login',
				'XenForo_ControllerPublic_Logout',
				'XenForo_ControllerPublic_Register',

				'XenForo_DataWriter_DiscussionMessage_Post',

				'XenForo_Model_Alert',
				'XenForo_Model_Category',
				'XenForo_Model_Conversation',
				'XenForo_Model_Forum',
				'XenForo_Model_Post',
				'XenForo_Model_Thread',
				'XenForo_Model_User',

				'XenForo_Search_DataHandler_Post',
				'XenForo_Search_DataHandler_Thread',
		);

		if (in_array($class, $classes))
		{
			$extend[] = 'bdApi_' . $class;
		}
	}

	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		// initializes the core template helper object
		// in the future, we may have different template helpers for public/admin/api context
		$templateHelper = bdApi_Template_Helper_Core::getInstance();

		// register the helper methods in the format `bdApi_<method_name>`
		$templateHelperReflector = new ReflectionClass(get_class($templateHelper));
		$methods = $templateHelperReflector->getMethods();
		foreach ($methods as $method)
		{
			if (!($method->getModifiers() & ReflectionMethod::IS_PUBLIC)
			OR ($method->getModifiers() & ReflectionMethod::IS_STATIC))
			{
				// ignore non-public instance methods
				continue;
			}

			$methodName = $method->getName();
			$helperCallbackName = utf8_strtolower('bdApi_' . $methodName);
			XenForo_Template_Helper_Core::$helperCallbacks[$helperCallbackName] = array($templateHelper, $methodName);
		}
	}

	public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if ($templateName == 'account_wrapper')
		{
			$template->preloadTemplate('bdapi_account_wrapper_sidebar');
		}
	}

	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		switch ($hookName)
		{
			case 'account_wrapper_sidebar_settings':
				$ourTemplate = $template->create('bdapi_account_wrapper_sidebar', $template->getParams());
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