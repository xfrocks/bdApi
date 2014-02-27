<?php

class bdApi_XenForo_ControllerPublic_Logout extends XFCP_bdApi_XenForo_ControllerPublic_Logout
{
	public function getDynamicRedirect($fallbackUrl = false, $useReferrer = true)
	{
		$input = $this->_input->filter(array(
			'redirect' => XenForo_Input::STRING,
			'timestamp' => XenForo_Input::UINT,
			'md5' => XenForo_Input::STRING,
		));

		if (md5($input['redirect']) === bdApi_Crypt::decryptTypeOne($input['md5'], $input['timestamp']))
		{
			return $input['redirect'];
		}

		return parent::getDynamicRedirect($fallbackUrl, $useReferrer);
	}

}
