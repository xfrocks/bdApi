<?php

class bdApi_ControllerApi_Tool extends bdApi_ControllerApi_Abstract
{

	public function actionPostLink()
	{
		$type = $this->_input->filterSingle('type', XenForo_Input::STRING, array('default' => 'public'));
		$route = $this->_input->filterSingle('route', XenForo_Input::STRING, array('default' => 'index'));

		switch ($type)
		{
			case 'admin':
				$link = bdApi_Link::buildAdminLink($route);
				break;
			case 'public':
			default:
				$link = bdApi_Link::buildPublicLink($route);
				break;
		}

		$data = array(
			'type' => $type,
			'route' => $route,
			'link' => $link,
		);

		return $this->responseData('bdApi_ViewApi_Tool_Link', $data);
	}

	/**
	 *
	 * @return XenForo_Model_Alert
	 */
	protected function _getAlertModel()
	{
		return $this->getModelFromCache('XenForo_Model_Alert');
	}

}
