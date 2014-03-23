<?php

class bdApiConsumer_XenForo_ControllerPublic_Logout extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Logout
{
	protected $_bdApiConsumer_beforeLogoutVisitorId = 0;

	public function bdApiConsumer_getBeforeLogoutVisitorId()
	{
		return $this->_bdApiConsumer_beforeLogoutVisitorId;
	}

	protected function _preDispatch($action)
	{
		$this->_bdApiConsumer_beforeLogoutVisitorId = XenForo_Visitor::getUserId();

		return parent::_preDispatch($action);
	}

}
