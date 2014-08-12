<?php

class bdApi_Template_Simulation_ViewRenderer extends XenForo_ViewRenderer_HtmlPublic
{
	protected static $_bdApi_dependencies = null;
	protected static $_bdApi_response = null;
	protected static $_bdApi_request = null;

	public function bdApi_getResponse()
	{
		return $this->_response;
	}

	public static function create()
	{
		if (self::$_bdApi_dependencies === null)
		{
			self::$_bdApi_dependencies = new bdApi_Template_Simulation_Dependencies();
		}

		if (self::$_bdApi_request === null)
		{
			self::$_bdApi_request = new Zend_Controller_Request_Http();
		}

		if (self::$_bdApi_response === null)
		{
			self::$_bdApi_response = new Zend_Controller_Response_Http();
		}

		return new bdApi_Template_Simulation_ViewRenderer(self::$_bdApi_dependencies, self::$_bdApi_response, self::$_bdApi_request);
	}

}
