<?php

class bdApi_ControllerApi_Index extends bdApi_ControllerApi_Abstract
{
	public function actionIndex()
	{
		$data = array(
			'links' => array(
				bdApi_Link::buildApiLink('users'),
				bdApi_Link::buildApiLink('nodes'),
				// bdApi_Link::buildApiLink('posts'), -- /posts requires thread_id
				bdApi_Link::buildApiLink('threads'),
			),
		);
		
		return $this->responseData('bdApi_ViewApi_Index', $data);
	}
}