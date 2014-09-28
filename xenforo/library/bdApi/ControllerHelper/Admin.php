<?php

class bdApi_ControllerHelper_Admin extends XenForo_ControllerHelper_Abstract
{
	public function prepareConditionsAndFetchOptions(array &$conditions, array &$fetchOptions)
	{
		$filterView = false;
		$conditions['expired'] = false;
		$fetchOptions['page'] = $this->_controller->getInput()->filterSingle('page', XenForo_Input::UINT);
		$fetchOptions['limit'] = 50;
		$pageNavParams = array();

		$clientId = $this->_controller->getInput()->filterSingle('client_id', XenForo_Input::STRING);
		if (!empty($clientId))
		{
			$client = $this->_controller->getModelFromCache('bdApi_Model_Client')->getClientById($clientId);
			if (!empty($client))
			{
				$conditions['client_id'] = $client['client_id'];
				$pageNavParams['client_id'] = $client['client_id'];
			}
		}

		$filter = $this->_controller->getInput()->filterSingle('_filter', XenForo_Input::ARRAY_SIMPLE);
		if ($filter && isset($filter['value']))
		{
			if (!$filterView AND !($this->_controller instanceof bdApi_ControllerAdmin_Subscription))
			{
				$users = $this->_controller->getModelFromCache('XenForo_Model_User')->getUsers(array('username' => array(
						$filter['value'],
						empty($filter['prefix']) ? 'lr' : 'r'
					)));
				if (!empty($users))
				{
					$conditions['user_id'] = array_keys($users);
					$filterView = true;
				}
			}

			if (!$filterView)
			{
				$conditions['filter'] = array(
					$filter['value'],
					empty($filter['prefix']) ? 'lr' : 'r'
				);
				$filterView = true;
			}
		}

		return array(
			'client' => !empty($client) ? $client : null,
			'page' => $fetchOptions['page'],
			'perPage' => $fetchOptions['limit'],
			'pageNavParams' => $pageNavParams,

			'filterView' => $filterView,
		);
	}

}
