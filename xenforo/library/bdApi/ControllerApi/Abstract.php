<?php

abstract class bdApi_ControllerApi_Abstract extends XenForo_ControllerPublic_Abstract
{
    const FIELDS_FILTER_NONE = 0;
    const FIELDS_FILTER_INCLUDE = 0x01;
    const FIELDS_FILTER_EXCLUDE = 0x02;

    const SPAM_RESULT_ALLOWED = 'allowed';
    const SPAM_RESULT_MODERATED = 'moderated';
    const SPAM_RESULT_DENIED = 'denied';

    protected $_fieldsFilterType = false;
    protected $_fieldsFilterInclude = array();
    protected $_fieldsFilterExclude = array();
    protected $_fieldsFilterExcludeHasWildcards = false;
    protected $_fieldsFilterDefaults = array();

    public function actionOptions()
    {
        $cors = $this->_request->getHeader('Access-Control-Request-Method');
        if (!empty($cors)) {
            return $this->responseData('bdApi_ViewApi_Helper_Options');
        }

        $action = $this->_input->filterSingle('action', XenForo_Input::STRING);
        $action = str_replace(array('-', '/'), ' ', utf8_strtolower($action));
        $action = str_replace(' ', '', utf8_ucwords($action));

        $methods = array();

        /* @var $fc XenForo_FrontController */
        $fc = XenForo_Application::get('_bdApi_fc');

        XenForo_Application::set('_bdApi_disableBatch', true);

        foreach (array(
                     'Get',
                     'Post',
                     'Put'
                 ) as $method) {
            $controllerMethod = sprintf('action%s%s', $method, $action);

            if (is_callable(array($this, $controllerMethod))) {
                $method = utf8_strtoupper($method);
                $methods[$method] = array();

                bdApi_Input::bdApi_resetFilters();

                $routeMatch = new XenForo_RouteMatch($this->_routeMatch->getControllerName(),
                    sprintf('%s-%s', $method, $action));

                try {
                    $fc->dispatch($routeMatch);
                } catch (Exception $e) {
                    // ignore
                }

                $params = bdApi_Input::bdApi_getFilters();
                foreach (array_keys($params) as $paramKey) {
                    if (in_array($paramKey, array(
                        'fields_include',
                        'fields_exclude',
                        'limit',
                        'locale',
                        'page',
                    ), true)) {
                        // system wide params, ignore
                        unset($params[$paramKey]);
                        continue;
                    }

                    if (!isset($_GET[$paramKey])
                        && $this->_input->inRequest($paramKey)
                    ) {
                        // apparently this param is set by the route class
                        unset($params[$paramKey]);
                        continue;
                    }
                }

                ksort($params);
                $methods[$method]['parameters'] = array_values($params);
            }
        }

        $allowedMethods = array_keys($methods);
        $allowedMethods[] = 'OPTIONS';
        $this->_response->setHeader('Allow', implode(',', $allowedMethods));

        return $this->responseData('bdApi_ViewApi_Helper_Options', $methods);
    }

    /**
     * Builds are response with specified data. Basically it's the same
     * XenForo_ControllerPublic_Abstract::responseView() but with the
     * template name removed so only view name and data array is available.
     * Also, the data has some rules enforced to make a good response.
     *
     * @param string $viewName
     * @param array $data
     *
     * @return XenForo_ControllerResponse_View
     */
    public function responseData($viewName, array $data = array())
    {
        return parent::responseView($viewName, 'DEFAULT', $data);
    }

    /**
     * @param array $errors
     * @param int $responseCode
     * @param array $containerParams
     *
     * @return XenForo_ControllerResponse_Error
     */
    public function responseErrors(array $errors, $responseCode = 200, array $containerParams = array())
    {
        return parent::responseError(reset($errors), $responseCode, $containerParams);
    }

    /**
     * Filters data for many resources.
     * This method name had been prefixed with "_" before it was updated to public visibility.
     * The name is kept for backward compatibility.
     *
     * @param array $resourcesData
     * @param array $prefixes
     * @return array
     */
    public function _filterDataMany(array $resourcesData, array $prefixes = array())
    {
        $filtered = array();

        foreach ($resourcesData as $key => $resourceData) {
            $filtered[$key] = $this->_filterDataSingle($resourceData, $prefixes);
        }

        return $filtered;
    }

    /**
     * Filters data for one resource.
     * This method name had been prefixed with "_" before it was updated to public visibility.
     * The name is kept for backward compatibility.
     *
     * @param array $resourceData
     * @param array $prefixes
     * @return array
     */
    public function _filterDataSingle(array $resourceData, array $prefixes = array())
    {
        $this->_prepareFieldsFilter();

        if ($this->_fieldsFilterType === self::FIELDS_FILTER_NONE) {
            return $resourceData;
        }

        $filtered = array();
        foreach (array_keys($resourceData) as $field) {
            if (!is_int($field) && $this->_isFieldExcluded($field, $prefixes)) {
                continue;
            }

            if (is_array($resourceData[$field]) && count($resourceData[$field]) > 0) {
                $_prefixes = $prefixes;
                if (!is_int($field)) {
                    $_prefixes[] = $field;
                }
                $_filtered = $this->_filterDataSingle($resourceData[$field], $_prefixes);
                if (count($_filtered) > 0) {
                    $filtered[$field] = $_filtered;
                }
            } else {
                $filtered[$field] = $resourceData[$field];
            }
        }

        return $filtered;
    }

    /**
     * Checks if a field is specifically requested to be included.
     * This method name had been prefixed with "_" before it was updated to public visibility.
     * The name is kept for backward compatibility.
     *
     * @param $field
     * @param array $prefixes
     * @return bool
     */
    public function _isFieldIncluded($field, array $prefixes = array())
    {
        $this->_prepareFieldsFilter();

        if (!($this->_fieldsFilterType & self::FIELDS_FILTER_INCLUDE)) {
            return false;
        }

        $pattern = $field;
        if (count($prefixes)) {
            $pattern = sprintf('%s.%s', implode('.', $prefixes), $field);
        }
        $patternAndDot = $pattern . '.';
        $patternAndDotLength = strlen($patternAndDot);

        foreach ($this->_fieldsFilterInclude as $_field) {
            if ($_field === $pattern
                || substr($_field, 0, $patternAndDotLength) === $patternAndDot
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a field is specifically requested to be excluded.
     * This method name had been prefixed with "_" before it was updated to public visibility.
     * The name is kept for backward compatibility.
     *
     * @param $field
     * @param array $prefixes
     * @return bool
     */
    public function _isFieldExcluded($field, array $prefixes = array())
    {
        $this->_prepareFieldsFilter();

        if ($this->_fieldsFilterType & self::FIELDS_FILTER_INCLUDE) {
            if ($this->_isFieldIncluded($field, $prefixes)) {
                return false;
            }

            $includeDefault = false;
            $_prefixes = $prefixes;
            while (true) {
                $_prefixesStr = implode('.', $_prefixes);
                if (isset($this->_fieldsFilterDefaults[$_prefixesStr])) {
                    if ($this->_fieldsFilterDefaults[$_prefixesStr]) {
                        $includeDefault = true;
                    }
                    break;
                }

                if (empty($_prefixes)) {
                    break;
                } else {
                    array_pop($_prefixes);
                }
            }
            if (!$includeDefault) {
                return true;
            }
        }

        if (!($this->_fieldsFilterType & self::FIELDS_FILTER_EXCLUDE)) {
            return false;
        }

        $pattern = $field;
        if (count($prefixes)) {
            $pattern = sprintf('%s.%s', implode('.', $prefixes), $field);
        }
        $wildcardPattern = null;
        if ($this->_fieldsFilterExcludeHasWildcards) {
            $wildcardPattern = sprintf('*.%s', $field);
        }

        foreach ($this->_fieldsFilterExclude as $_field) {
            if ($_field === $pattern) {
                return true;
            }

            if ($wildcardPattern !== null
                && $_field === $wildcardPattern
            ) {
                return true;
            }
        }

        return false;
    }

    protected function _prepareFieldsFilter()
    {
        if ($this->_fieldsFilterType === false) {
            $this->_fieldsFilterType = self::FIELDS_FILTER_NONE;

            // use values from this request specifically
            $include = $this->_input->filterSingle('fields_include', XenForo_Input::STRING);
            $exclude = $this->_input->filterSingle('fields_exclude', XenForo_Input::STRING);
            if (empty($include) && empty($exclude)) {
                // use values from $_GET, useful with /search or /batch
                $include = filter_input(INPUT_GET, 'fields_include');
                $exclude = filter_input(INPUT_GET, 'fields_exclude');
            }

            if (!empty($include)) {
                $this->_fieldsFilterType |= self::FIELDS_FILTER_INCLUDE;
                foreach (explode(',', $include) as $field) {
                    $field = trim($field);
                    $prefixes = explode('.', $field);
                    $_field = array_pop($prefixes);
                    $_prefixes = implode('.', $prefixes);
                    if ($_field === '*') {
                        $this->_fieldsFilterDefaults[$_prefixes] = true;
                        if (!empty($_prefixes)) {
                            $this->_fieldsFilterInclude[] = $_prefixes;
                        }
                    } else {
                        $this->_fieldsFilterInclude[] = $field;
                        $this->_fieldsFilterDefaults[$field] = true;
                        if (!isset($this->_fieldsFilterDefaults[$_prefixes])) {
                            $this->_fieldsFilterDefaults[$_prefixes] = false;
                        }
                    }
                }
            }

            if (!empty($exclude)) {
                $this->_fieldsFilterType |= self::FIELDS_FILTER_EXCLUDE;
                foreach (explode(',', $exclude) as $field) {
                    $field = trim($field);
                    $this->_fieldsFilterExclude[] = $field;

                    if (strpos($field, '*') !== false) {
                        $this->_fieldsFilterExcludeHasWildcards = true;
                    }
                }
            }
        }
    }

    /**
     * Try to check submitted data for spam.
     * <code>$data</code> should have <code>'content'</code>
     * and <code>'content_type'</code> for optimal operation.
     *
     * @param array $data
     * @return string one of the SPAM_RESULT_* constants
     */
    protected function _spamCheck(array $data)
    {
        if (XenForo_Application::$versionId < 1020000) {
            return self::SPAM_RESULT_ALLOWED;
        }

        /** @var XenForo_Model_SpamPrevention $spamModel */
        $spamModel = $this->getModelFromCache('XenForo_Model_SpamPrevention');
        $spamResult = self::SPAM_RESULT_ALLOWED;

        if ($spamModel->visitorRequiresSpamCheck()) {
            if (isset($data['content'])) {
                switch ($spamModel->checkMessageSpam($data['content'], $data, $this->_request)) {
                    case XenForo_Model_SpamPrevention::RESULT_ALLOWED:
                        $spamResult = self::SPAM_RESULT_ALLOWED;
                        break;
                    case XenForo_Model_SpamPrevention::RESULT_MODERATED:
                        $spamResult = self::SPAM_RESULT_MODERATED;
                        break;
                    case XenForo_Model_SpamPrevention::RESULT_DENIED:
                        $spamResult = self::SPAM_RESULT_DENIED;
                        break;
                }
            }

            switch ($spamResult) {
                case self::SPAM_RESULT_MODERATED:
                case self::SPAM_RESULT_DENIED;
                    if (isset($data['content_type'])) {
                        $contentId = null;
                        if (isset($data['content_id'])) {
                            $contentId = $data['content_id'];
                        }

                        $spamModel->logSpamTrigger($data['content_type'], $contentId);
                    }
                    break;
            }
        }

        return $spamResult;
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
     * @return string required scope. One of the SCOPE_* constant in
     * bdApi_Model_OAuth2
     */
    protected function _getScopeForAction($action)
    {
        if (strpos($action, 'Post') === 0) {
            return bdApi_Model_OAuth2::SCOPE_POST;
        } elseif (strpos($action, 'Put') === 0) {
            // TODO: separate scope?
            return bdApi_Model_OAuth2::SCOPE_POST;
        } elseif (strpos($action, 'Delete') === 0) {
            // TODO: separate scope?
            return bdApi_Model_OAuth2::SCOPE_POST;
        } else {
            return bdApi_Model_OAuth2::SCOPE_READ;
        }
    }

    /**
     * Helper to check for the required scope and throw an exception
     * if it could not be found.
     * @param $scope
     * @throws XenForo_ControllerResponse_Exception
     * @throws Zend_Exception
     */
    protected function _assertRequiredScope($scope)
    {
        if (empty($scope)) {
            // no scope is required
            return;
        }

        /* @var $session bdApi_Session */
        $session = XenForo_Application::get('session');

        if (!$session->checkScope($scope)) {
            $oauthTokenText = $session->getOAuthTokenText();

            if (empty($oauthTokenText)) {
                /** @var bdApi_Model_OAuth2 $oauth2Model */
                $oauth2Model = XenForo_Model::create('bdApi_Model_OAuth2');
                $controllerResponse = $oauth2Model->getServer()->getErrorControllerResponse($this);

                if (empty($controllerResponse)) {
                    $controllerResponse = $this->responseError(new XenForo_Phrase('bdapi_authorize_error_invalid_or_expired_access_token'), 403);
                }
            }

            if (empty($controllerResponse)) {
                $controllerResponse = $this->responseError(new XenForo_Phrase('bdapi_authorize_error_scope_x_not_granted', array('scope' => $scope)), 403);
            }

            throw $this->responseException($controllerResponse);
        }
    }

    protected function _assertAdminPermission($permissionId)
    {
        $this->_assertRequiredScope(bdApi_Model_OAuth2::SCOPE_MANAGE_SYSTEM);

        if (!XenForo_Visitor::getInstance()->hasAdminPermission($permissionId)) {
            throw $this->responseException($this->responseNoPermission());
        }
    }

    protected function _assertBoardActive($action)
    {
        parent::_assertBoardActive($action);

        if (strpos($action, 'Get') !== 0
            && XenForo_Application::isRegistered('_bdCloudServerHelper_readonly')
        ) {
            $response = $this->responseError(new XenForo_Phrase('bdcsh_forum_is_currently_read_only'), 503);
            throw $this->responseException($response);
        }
    }

    public function responseNoPermission()
    {
        return $this->responseReroute('bdApi_ControllerApi_Error', 'no-permission');
    }

    protected function _assertRegistrationRequired()
    {
        if (!XenForo_Visitor::getUserId()) {
            throw $this->responseException($this->responseReroute('bdApi_ControllerApi_Error', 'registration-required'));
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
        if (XenForo_Application::isRegistered('session')) {
            return;
        }

        bdApi_Session::startApiSession($this->_request);
    }


    public function updateSessionActivity($controllerResponse, $controllerName, $action)
    {
        if (!bdApi_Option::get('trackSession')) {
            return;
        }

        if (!$this->_request->isGet()) {
            return;
        }

        $session = bdApi_Data_Helper_Core::safeGetSession();
        if (empty($session)) {
            return;
        }

        $visitorUserId = XenForo_Visitor::getUserId();
        if ($visitorUserId === 0) {
            return;
        }

        if ($controllerResponse instanceof XenForo_ControllerResponse_Reroute) {
            return;
        } elseif ($controllerResponse instanceof XenForo_ControllerResponse_Redirect) {
            return;
        }

        $params = $this->_request->getUserParams();
        $this->_prepareSessionActivityForApi($controllerName, $action, $params);

        /** @var XenForo_Model_User $userModel */
        $userModel = $this->getModelFromCache('XenForo_Model_User');
        $userModel->updateSessionActivity(
            $visitorUserId, $this->_request->getClientIp(false),
            $controllerName, $action, 'valid', $params
        );
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        $controllerName = 'bdApi_ControllerApi_Index';
        $action = '';
        $params = array();

        $session = bdApi_Data_Helper_Core::safeGetSession();
        if (!empty($session)) {
            $params['client_id'] = $session->getOAuthClientId();
        }
    }

    protected function _checkCsrf($action)
    {
        // do not check csrf for api requests
        self::$_executed['csrf'] = true;
        return;
    }

    protected function _postDispatch($controllerResponse, $controllerName, $action)
    {
        $this->_logRequest($controllerResponse, $controllerName, $action);

        parent::_postDispatch($controllerResponse, $controllerName, $action);
    }

    protected function _logRequest($controllerResponse, $controller, $action)
    {
        $requestMethod = $this->_request->getMethod();
        $requestUri = $this->_request->getRequestUri();
        $requestData = $this->_request->getParams();
        if ($controllerResponse instanceof XenForo_ControllerResponse_Abstract) {
            if ($controllerResponse instanceof XenForo_ControllerResponse_Redirect) {
                $responseCode = 301;
                $responseOutput = array_merge($controllerResponse->redirectParams, array(
                    'redirectType' => $controllerResponse->redirectType,
                    'redirectMessage' => $controllerResponse->redirectMessage,
                    'redirectUri' => $controllerResponse->redirectTarget,
                ));
            } else {
                $responseCode = $controllerResponse->responseCode;
                $responseOutput = $this->_getResponseOutput($controllerResponse);
            }
        } else {
            $responseCode = $this->_response->getHttpResponseCode();
            $responseOutput = array(
                'raw' => $controllerResponse,
                'controller' => $controller,
                'action' => $action,
            );
        }

        if ($responseOutput !== false) {
            /* @var $logModel bdApi_Model_Log */
            $logModel = $this->getModelFromCache('bdApi_Model_Log');
            $logModel->logRequest($requestMethod, $requestUri, $requestData, $responseCode, $responseOutput);
        }

        return true;
    }

    protected function _getResponseOutput(XenForo_ControllerResponse_Abstract $controllerResponse)
    {
        $responseOutput = array();

        if ($controllerResponse instanceof XenForo_ControllerResponse_View) {
            $responseOutput = $controllerResponse->params;
        } elseif ($controllerResponse instanceof XenForo_ControllerResponse_Error) {
            $responseOutput = array('error' => $controllerResponse->errorText);
        } elseif ($controllerResponse instanceof XenForo_ControllerResponse_Exception) {
            $responseOutput = $this->_getResponseOutput($controllerResponse->getControllerResponse());
        } elseif ($controllerResponse instanceof XenForo_ControllerResponse_Message) {
            $responseOutput = array('message' => $controllerResponse->message);
        } elseif ($controllerResponse instanceof XenForo_ControllerResponse_Reroute) {
            return false;
        }

        return $responseOutput;
    }

    protected function _setDeprecatedHeaders($newMethod, $newLink)
    {
        $this->_response->setHeader('X-Api-Deprecated', sprintf(
            'newMethod=%s, newLink=%s',
            strtoupper($newMethod),
            $newLink
        ), true);
    }

}
