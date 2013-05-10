<?php

abstract class bdApi_ControllerApi_Abstract extends XenForo_ControllerPublic_Abstract
{
	const FIELDS_FILTER_NONE = '';
	const FIELDS_FILTER_INCLUDE = 'include';
	const FIELDS_FILTER_EXCLUDE = 'exclude';

	protected $_fieldsFilterType = false;
	protected $_fieldsFilterList = array();

	/**
	 * Builds are response with specified data. Basically it's the same
	 * XenForo_ControllerPublic_Abstract::responseView() but with the
	 * template name removed so only view name and data array is available.
	 * Also, the data has some rules enforced to make a good response.
	 *
	 * @param string $viewName
	 * @param array $data
	*/
	public function responseData($viewName, array $data = array())
	{
		return parent::responseView($viewName, 'DEFAULT', $data);
	}

	/**
	 * Filters data for many resources
	 *
	 * @param array $resourcesData
	 * @return array
	 */
	protected function _filterDataMany(array $resourcesData)
	{
		$filtered = array();

		foreach ($resourcesData as $key => $resourceData)
		{
			$filtered[$key] = $this->_filterDataSingle($resourceData);
		}

		return $filtered;
	}

	/**
	 * Filters data for one resource
	 *
	 * @param array $resourceData
	 * @return array
	 */
	protected function _filterDataSingle(array $resourceData, array $prefixes = array())
	{
		$this->_prepareFieldsFilter();

		switch ($this->_fieldsFilterType)
		{
			case self::FIELDS_FILTER_INCLUDE:
				$filtered = array();
				foreach (array_keys($resourceData) as $field)
				{
					$fieldWithPrefixes = implode('.', array_merge($prefixes, array($field)));

					if (in_array($fieldWithPrefixes, $this->_fieldsFilterList))
					{
						$filtered[$field] = $resourceData[$field];
					}
					else
					{
						if (is_array($resourceData[$field]))
						{
							$_prefixes = $prefixes;
							if (!is_int($field))
							{
								$_prefixes[] = $field;
							}
							$_filtered = $this->_filterDataSingle($resourceData[$field], $_prefixes);
							if (!empty($_filtered))
							{
								$filtered[$field] = $_filtered;
							}
						}
					}
				}
				break;
			case self::FIELDS_FILTER_EXCLUDE:
				$filtered = $resourceData;
				foreach (array_keys($resourceData) as $field)
				{
					$fieldWithPrefixes = implode('.', array_merge($prefixes, array($field)));

					if (in_array($fieldWithPrefixes, $this->_fieldsFilterList))
					{
						unset($filtered[$field]);
					}
					else
					{
						if (is_array($resourceData[$field]))
						{
							$_prefixes = $prefixes;
							if (!is_int($field))
							{
								$_prefixes[] = $field;
							}
							$_filtered = $this->_filterDataSingle($resourceData[$field], $_prefixes);
							if (!empty($_filtered))
							{
								$filtered[$field] = $_filtered;
							}
							else
							{
								unset($filtered[$field]);
							}
						}
					}
				}
				break;
			default:
				$filtered = $resourceData;
		}

		return $filtered;
	}

	protected function _isFieldExcluded($field)
	{
		$this->_prepareFieldsFilter();

		$fieldAndDot = sprintf('%s.', $field);
		$fieldAndDotStrlen = strlen($fieldAndDot);

		switch ($this->_fieldsFilterType)
		{
			case self::FIELDS_FILTER_INCLUDE:
				foreach ($this->_fieldsFilterList as $_field)
				{
					if ($_field == $field)
					{
						return false;
					}

					if (substr($_field, 0, $fieldAndDotStrlen) == $fieldAndDot)
					{
						return false;
					}
				}

				return true;
			case self::FIELDS_FILTER_EXCLUDE:
				return in_array($field, $this->_fieldsFilterList);
		}

		return false;
	}

	protected function _prepareFieldsFilter()
	{
		if ($this->_fieldsFilterType === false)
		{
			$this->_fieldsFilterType = self::FIELDS_FILTER_NONE;

			$include = $this->_input->filterSingle('fields_include', XenForo_Input::STRING);
			if (!empty($include))
			{
				$this->_fieldsFilterType = self::FIELDS_FILTER_INCLUDE;
				foreach (explode(',', $include) as $field)
				{
					$this->_fieldsFilterList[] = trim($field);
				}
			}
			else
			{
				$exclude = $this->_input->filterSingle('fields_exclude', XenForo_Input::STRING);
				if (!empty($exclude))
				{
					$this->_fieldsFilterType = self::FIELDS_FILTER_EXCLUDE;
					foreach (explode(',', $exclude) as $field)
					{
						$this->_fieldsFilterList[] = trim($field);
					}
				}
			}
		}
	}

	/**
	 * Gets the required scope for a controller action. By default,
	 * all API GET actions will require the read scope, POST actions will require
	 * the post scope.
	 *
	 * Special case: if no OAuth token is specified (the session
	 * will be setup as guest), GET actions won't require the read scope anymore.
	 * That means guest-permission API requests will have the read scope
	 * automatically.
	 *
	 * @param string $action
	 *
	 * @return string required scope. One of the SCOPE_* constant in bdApi_Model_OAuth2
	 */
	protected function _getScopeForAction($action)
	{
		if (strpos($action, 'Post') === 0)
		{
			return bdApi_Model_OAuth2::SCOPE_POST;
		}
		elseif (strpos($action, 'Put') === 0)
		{
			// TODO: separate scope?
			return bdApi_Model_OAuth2::SCOPE_POST;
		}
		elseif (strpos($action, 'Delete') === 0)
		{
			// TODO: separate scope?
			return bdApi_Model_OAuth2::SCOPE_POST;
		}
		else
		{
			if (XenForo_Visitor::getUserId() > 0)
			{
				return bdApi_Model_OAuth2::SCOPE_READ;
			}
			else
			{
				return false;
			}
		}
	}

	/**
	 * Helper to check for the required scope and throw an exception
	 * if it could not be found.
	 */
	protected function _assertRequiredScope($scope)
	{
		if (empty($scope))
		{
			// no scope is required
			return;
		}

		/* @var $session bdApi_Session */
		$session = XenForo_Application::get('session');

		$oauthTokenText = $session->getOAuthTokenText();
		if (empty($oauthTokenText))
		{
			throw $this->responseException(
					$this->responseError(new XenForo_Phrase('bdapi_authorize_error_invalid_or_expired_access_token'), 403)
			);
		}

		if (!$session->checkScope($scope))
		{
			throw $this->responseException(
					$this->responseError(new XenForo_Phrase('bdapi_authorize_error_scope_x_not_granted', array('scope' => $scope)), 403)
			);
		}
	}

	public function responseView($viewName, $templateName = 'DEFAULT', array $params = array(), array $containerParams = array())
	{
		throw new XenForo_Exception('bdApi_ControllerApi_Abstract::responseView() is not supported.');
	}

	public function responseRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
	{
		$data = array(
				'redirect' => array(
						'type' => $redirectType,
						'target' => $redirectTarget,
				),
		);

		if ($redirectMessage !== null)
		{
			$data['redirect']['message'] = $redirectMessage;
		}

		return $this->responseData('', $data);
	}

	public function responseNoPermission()
	{
		return $this->responseReroute('bdApi_ControllerApi_Error', 'noPermission');
	}

	public function updateSessionActivity($controllerResponse, $controllerName, $action)
	{
		// disable session activity for api requests
		return;
	}

	protected function _assertRegistrationRequired()
	{
		if (!XenForo_Visitor::getUserId())
		{
			throw $this->responseException(
					$this->responseReroute('bdApi_ControllerApi_Error', 'registrationRequired')
			);
		}
	}

	protected function _preDispatch($action)
	{
		$requiredScope = $this->_getScopeForAction($action);
		$this->_assertRequiredScope($requiredScope);

		parent::_preDispatch($action);
	}

	protected function _setupSession($action)
	{
		if (XenForo_Application::isRegistered('session'))
		{
			return;
		}

		$session = bdApi_Session::startApiSession($this->_request);
	}

	protected function _checkCsrf($action)
	{
		// do not check csrf for api requests
		self::$_executed['csrf'] = true;
		return;
	}

	protected function _postDispatch($controllerResponse, $controllerName, $action)
	{
		$responseCode = $controllerResponse->responseCode;
		$responseOutput = $this->_getResponseOutput($controllerResponse);

		$this->getModelFromCache('bdApi_Model_Log')->logRequest($responseCode, $responseOutput);

		return parent::_postDispatch($controllerResponse, $controllerName, $action);
	}

	protected function _getResponseOutput(XenForo_ControllerResponse_Abstract $controllerResponse)
	{
		$responseOutput = array();

		if ($controllerResponse instanceof XenForo_ControllerResponse_View)
		{
			$responseOutput = $controllerResponse->params;
		}
		elseif ($controllerResponse instanceof XenForo_ControllerResponse_Error)
		{
			$responseOutput = array(
					'error' => $controllerResponse->errorText,
			);
		}
		elseif ($controllerResponse instanceof XenForo_ControllerResponse_Exception)
		{
			$responseOutput = $this->_getResponseOutput($controllerResponse->getControllerResponse());
		}
		elseif ($controllerResponse instanceof XenForo_ControllerResponse_Message)
		{
			$responseOutput = array(
					'message' => $controllerResponse->message,
			);
		}

		return $responseOutput;
	}
}