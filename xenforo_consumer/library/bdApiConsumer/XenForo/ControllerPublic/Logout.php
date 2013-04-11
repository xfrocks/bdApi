<?php
class bdApiConsumer_XenForo_ControllerPublic_Logout extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Logout
{
	public function actionIndex()
	{
		$response = parent::actionIndex();

		if ($response instanceof XenForo_ControllerResponse_Redirect)
		{
			XenForo_Helper_Cookie::setCookie(
				'bdApiConsumer_logoutTime',
				XenForo_Application::$time,
				60 // a minute
			);
		}

		return $response;
	}
}