<?php

class bdApi_ViewAdmin_Client_List extends XenForo_ViewAdmin_Base
{
	public function renderJson()
	{
		if (!empty($this->_params['filterView']))
		{
			$this->_templateName = 'bdapi_client_list_items';
		}
	
		return null;
	}
}