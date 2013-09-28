<?php

class bdApi_ViewRenderer_Json extends XenForo_ViewRenderer_Json
{
	public function renderError($error)
	{
		if (!is_array($error))
		{
			$error = array($error);
		}

		return self::jsonEncodeForOutput(array('errors' => $error));
	}

	public function renderMessage($message)
	{
		return self::jsonEncodeForOutput(array(
			'status' => 'ok',
			'message' => $message
		));
	}

	public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
	{
		$viewOutput = $this->renderViewObject($viewName, 'Json', $params, $templateName);

		if (is_array($viewOutput))
		{
			return self::jsonEncodeForOutput($viewOutput);
		}
		else
		if ($viewOutput === null)
		{
			return self::jsonEncodeForOutput($this->getDefaultOutputArray($viewName, $params, $templateName));
		}
		else
		{
			return $viewOutput;
		}
	}

	public function getDefaultOutputArray($viewName, $params, $templateName)
	{
		return $params;
	}

	public static function jsonEncodeForOutput($input, $addDefaultParams = true)
	{
		if ($addDefaultParams)
		{
			self::_addDefaultParams($input);
		}

		foreach (array_keys($input) as $inputKey)
		{
			if (strpos($inputKey, '_WidgetFramework') === 0)
			{
				// filter out [bd] Widget Framework junk
				unset($input[$inputKey]);
			}
		}

		return XenForo_ViewRenderer_Json::jsonEncodeForOutput($input, false);
	}

	protected static function _addDefaultParams(array &$params = array())
	{
		bdApi_Data_Helper_Core::addDefaultResponse($params);
	}

}
