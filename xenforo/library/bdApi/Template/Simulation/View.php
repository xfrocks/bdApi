<?php

class bdApi_Template_Simulation_View extends XenForo_View
{
	protected static $_bdApi_viewRenderer = null;

	public static function create()
	{
		if (self::$_bdApi_viewRenderer === null)
		{
			self::$_bdApi_viewRenderer = bdApi_Template_Simulation_ViewRenderer::create();
		}

		return new bdApi_Template_Simulation_View(self::$_bdApi_viewRenderer, self::$_bdApi_viewRenderer->bdApi_getResponse());
	}

}
