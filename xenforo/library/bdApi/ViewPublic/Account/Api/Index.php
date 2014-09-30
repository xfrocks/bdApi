<?php

class bdApi_ViewPublic_Account_Api_Index extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
		if (!empty($this->_params['tokens']))
		{
			foreach ($this->_params['tokens'] as &$token)
			{
				$token['_scopes'] = bdApi_Template_Helper_Core::getInstance()->scopeSplit($token['scope']);
			}
		}
	}

}
