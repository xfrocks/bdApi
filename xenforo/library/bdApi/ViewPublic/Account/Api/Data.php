<?php

class bdApi_ViewPublic_Account_Api_Data extends XenForo_ViewPublic_Base
{
	public function renderHtml()
	{
    header('Content-Type: application/x-javascript; charset=utf-8');
    $jsonp = sprintf(
      '%s(%s);',
      $this->_params['callback'],
      json_encode($this->_params['data'])
    );
		die($jsonp);
	}
}