<?php
class bdApiConsumer_Listener
{
	protected static $_commonTemplatesPreloaded = false;

	public static function load_class($class, array &$extend)
	{
		static $classes = array(
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
	}

	public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if (empty(self::$_commonTemplatesPreloaded))
		{
			$template->preloadTemplate('bdapi_consumer_login_bar_eauth_items');
			$template->preloadTemplate('bdapi_consumer_login_bar_eauth_set');
			self::$_commonTemplatesPreloaded = true;
		}

		if ($templateName === 'PAGE_CONTAINER')
		{
			if (bdApiConsumer_Option::get('_activated'))
			{
				// setting $eAuth in hook position login_bar_eauth_set doens't work
				// so we have to do it here. Risk: it will not work if the container template is changed
				// TODO: find a better way to do this
				$params['eAuth'] = 1;
			}
		}
		
		switch ($templateName)
		{
			case 'login':
			case 'error_with_login':
				$template->preloadTemplate('bdapi_consumer_' . $templateName);
				break;
		}
	}

	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		switch ($hookName)
		{
			case 'login_bar_eauth_items':
			case 'login_bar_eauth_set':
				$ourTemplate = $template->create('bdapi_consumer_' . $hookName, $template->getParams());

				if ($hookName === 'login_bar_eauth_items')
				{
					$ourTemplate->setParam('providers', bdApiConsumer_Option::get('_providers'));
				}

				$rendered = $ourTemplate->render();
				$contents .= $rendered;
				break;
		}
	}
	
	public static function template_post_render($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
	{
		switch ($templateName)
		{
			case 'login':
			case 'error_with_login':
				$ourTemplate = $template->create('bdapi_consumer_' . $templateName, $template->getParams());
				$ourTemplate->setParam('providers', bdApiConsumer_Option::get('_providers'));

				$rendered = $ourTemplate->render();
				$content .= $rendered;
				break;
		}
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += bdApiConsumer_FileSums::getHashes();
	}
}