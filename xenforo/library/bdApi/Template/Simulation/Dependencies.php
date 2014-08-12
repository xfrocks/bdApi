<?php

class bdApi_Template_Simulation_Dependencies extends XenForo_Dependencies_Public
{
	public function createTemplateObject($templateName, array $params = array())
	{
		if ($params)
		{
			$params = XenForo_Application::mapMerge($this->_defaultTemplateParams, $params);
		}
		else
		{
			$params = $this->_defaultTemplateParams;
		}

		return new bdApi_Template_Simulation_Template($templateName, $params);
	}

}
