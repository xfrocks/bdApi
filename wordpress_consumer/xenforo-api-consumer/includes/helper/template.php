<?php

function xfac_template_locateTemplate($templateNames, $requireOnce = false)
{
	$templateFile = '';

	if (!is_array($templateNames))
	{
		$templateNames = array($templateNames);
	}

	$stylesheetPath = STYLESHEETPATH . '/xenforo-api-consumer/';
	$templatePath = TEMPLATEPATH . '/xenforo-api-consumer/';
	$pluginPath = XFAC_PLUGIN_PATH . '/templates/';

	foreach ($templateNames as $templateName)
	{
		if (empty($templateName))
		{
			continue;
		}

		if (file_exists($stylesheetPath . $templateName))
		{
			$templateFile = $stylesheetPath . $templateName;
			break;
		}
		elseif (file_exists($templatePath . $templateName))
		{
			$templateFile = $templatePath . $templateName;
			break;
		}
		elseif (file_exists($pluginPath . $templateName))
		{
			$templateFile = $pluginPath . $templateName;
			break;
		}
	}

	return $templateFile;
}
