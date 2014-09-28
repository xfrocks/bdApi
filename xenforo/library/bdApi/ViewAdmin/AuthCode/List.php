<?php

class bdApi_ViewAdmin_AuthCode_List extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		if (!empty($this->_params['filterView']))
		{
			$this->_templateName = 'bdapi_auth_code_list_items';
		}
	
		return null;
	}
}