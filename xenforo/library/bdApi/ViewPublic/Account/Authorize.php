<?php

class bdApi_ViewPublic_Account_Authorize extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		$this->_params['authorizeScopes'] = array();
		
		if (!empty($this->_params['authorizeParams']['scope']))
		{
			$this->_params['authorizeScopes'] = explode(',', $this->_params['authorizeParams']['scope']);
			$this->_params['authorizeScopes'] = array_map('trim', $this->_params['authorizeScopes']);
		}
	}
}