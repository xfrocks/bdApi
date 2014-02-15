<?php
class bdApiConsumer_Listener
{
	protected static $_commonTemplatesPreloaded = false;

	public static function load_class($class, array &$extend)
	{
		static $classes = array(
			'XenForo_ControllerPublic_Account',
			'XenForo_ControllerPublic_Login',
			'XenForo_ControllerPublic_Logout',
			'XenForo_ControllerPublic_Register',
			'XenForo_Model_UserExternal',
		);

		if (in_array($class, $classes))
		{
			$extend[] = 'bdApiConsumer_' . $class;
		}
	}

	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		XenForo_Template_Helper_Core::$helperCallbacks['bdapiconsumer_getoption'] = array('bdApiConsumer_Option', 'get');
		XenForo_Template_Helper_Core::$helperCallbacks['bdapiconsumer_getprovidersdkjs'] = array('bdApiConsumer_Helper_Template', 'getProviderSdkJs');
	}

	public static function front_controller_pre_view(XenForo_FrontController $fc, XenForo_ControllerResponse_Abstract &$controllerResponse, XenForo_ViewRenderer_Abstract &$viewRenderer, array &$containerParams)
	{
		$cookieLogoutTime = XenForo_Helper_Cookie::getCookie('bdApiConsumer_logoutTime', $fc->getRequest());
		$containerParams['bdApiConsumer_logoutTime'] = $cookieLogoutTime;
	}

	public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if (empty(self::$_commonTemplatesPreloaded))
		{
			$template->preloadTemplate('bdapi_consumer_providers');
			$template->preloadTemplate('bdapi_consumer_page_container_head');
			self::$_commonTemplatesPreloaded = true;
		}
	}

	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		switch ($hookName)
		{
			case 'bdapi_consumer_providers':
				$params = array_merge($template->getParams(), $hookParams);
				$params['providers'] = bdApiConsumer_Option::getProviders();
				$ourTemplate = $template->create($hookName, $params);
				$contents = $ourTemplate->render();
				break;
			case 'page_container_head':
				$ourTemplate = $template->create('bdapi_consumer_' . $hookName, $template->getParams());
				$ourTemplate->setParam('providers', bdApiConsumer_Option::getProviders());

				$rendered = $ourTemplate->render();
				$contents .= $rendered;
				break;
		}
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += bdApiConsumer_FileSums::getHashes();
	}
}