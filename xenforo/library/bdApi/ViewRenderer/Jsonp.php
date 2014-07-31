<?php

class bdApi_ViewRenderer_Jsonp extends bdApi_ViewRenderer_Json
{
	public function renderError($error)
	{
		$json = parent::renderError($error);

		return self::jsonpForOutput($json);
	}

	public function renderMessage($message)
	{
		$json = parent::renderMessage($message);

		return self::jsonpForOutput($json);
	}

	public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
	{
		$json = parent::renderView($viewName, $params, $templateName, $subView);

		return self::jsonpForOutput($json);
	}

	public function jsonpForOutput($json)
	{
		$callback = 'callback';
		if (isset($_REQUEST['callback']))
		{
			$callback = $_REQUEST['callback'];
		}

		return sprintf('%s(%s);', $callback, $json);
	}

}
