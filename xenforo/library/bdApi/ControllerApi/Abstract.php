<?php

abstract class bdApi_ControllerApi_Abstract extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Builds are response with specified data. Basically it's the same
	 * XenForo_ControllerPublic_Abstract::responseView() but with the
	 * template name removed so only view name and data array is available.
	 * Also, the data has some rules enforced to make a good response. 
	 * 
	 * @param string $viewName
	 * @param array $data
	 */
	public function responseData($viewName, array $data = array())
	{
		return parent::responseView($viewName, 'DEFAULT', $data);
	}
	
	public function responseView($viewName, $templateName = 'DEFAULT', array $params = array(), array $containerParams = array())
	{
		throw new XenForo_Exception('bdApi_ControllerApi_Abstract::responseView() is not available.');
	}
	
	public function responseRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
	{
		$data = array(
			'redirect' => array(
				'type' => $redirectType,
				'target' => $redirectTarget,
			),
		);
		
		if ($redirectMessage !== null)
		{
			$data['redirect']['message'] = $redirectMessage;
		}
		
		return $this->responseData('', $data);
	}
	
	public function responseNoPermission()
	{
		return $this->responseReroute('bdApi_ControllerApi_Error', 'noPermission');
	}
	
	public function updateSessionActivity($controllerResponse, $controllerName, $action)
	{
		// disable session activity for api requests
		return;
	}
	
	protected function _checkCsrf($action)
	{
		// do not check csrf for api requests
		self::$_executed['csrf'] = true;
		return;
	}
}