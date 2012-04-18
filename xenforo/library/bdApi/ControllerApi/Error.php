<?php

class bdApi_ControllerApi_Error extends bdApi_ControllerApi_Abstract
{
	public function actionErrorNotFound()
	{
		if (XenForo_Application::debugMode())
		{
			$controllerName = $this->_request->getParam('_controllerName');

			if (empty($controllerName))
			{
				return $this->responseError(
					new XenForo_Phrase('controller_for_route_not_found', array
					(
						'routePath' => $this->_request->getParam('_origRoutePath'),
					)), 404
				);
			}
			else
			{
				return $this->responseError(
					new XenForo_Phrase('controller_x_does_not_define_action_y', array
					(
						'controller' => $controllerName,
						'action' => $this->_request->getParam('_action')
					)), 404
				);
			}
		}
		else 
		{
			return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
		}
	}

	public function actionErrorServer()
	{
		return $this->responseError(new XenForo_Phrase('server_error_occurred'), 500);
	}

	public function actionNoPermission()
	{
		return $this->responseError(new XenForo_Phrase('do_not_have_permission'), 403);
	}

	public function actionRegistrationRequired()
	{
		return $this->actionNoPermission();
	}

	public function actionBanned()
	{
		return $this->responseError(new XenForo_Phrase('you_have_been_banned'), 403);
	}

	public function actionBannedIp()
	{
		return $this->responseError(new XenForo_Phrase('your_ip_address_has_been_banned'), 403);
	}

	protected function _assertIpNotBanned() {}
	protected function _assertViewingPermissions($action) {}
	protected function _assertNotBanned() {}
	protected function _assertBoardActive($action) {}
	protected function _assertCorrectVersion($action) {}
	public function updateSessionActivity($controllerResponse, $controllerName, $action) {}
	
	protected function _getScopeForAction($action)
	{
		// no scope checking for this controller
		return false;
	}
}