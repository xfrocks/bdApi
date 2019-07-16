<?php

class bdApi_ViewRenderer_Json extends XenForo_ViewRenderer_Json
{
    public function renderError($error)
    {
        if ($this->_response->getHttpResponseCode() === 200) {
            // render errors with http 400 unless specified otherwise
            $this->_response->setHttpResponseCode(400);
        }

        bdApi_Data_Helper_Cors::addHeaders($this, $this->_response);

        if (!is_array($error)) {
            $error = array($error);
        }

        return self::jsonEncodeForOutput(array('errors' => $error));
    }

    public function renderMessage($message)
    {
        bdApi_Data_Helper_Cors::addHeaders($this, $this->_response);

        return self::jsonEncodeForOutput(array(
            'status' => 'ok',
            'message' => $message
        ));
    }

    public function renderRedirect(
        $redirectType,
        $redirectTarget,
        $redirectMessage = null,
        array $redirectParams = array()
    ) {
        bdApi_Data_Helper_Cors::addHeaders($this, $this->_response);

        switch ($redirectType) {
            case XenForo_ControllerResponse_Redirect::RESOURCE_CREATED:
            case XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED:
            case XenForo_ControllerResponse_Redirect::SUCCESS:
                $this->_response->setRedirect($redirectTarget, 303);
                break;

            case XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL:
                $this->_response->setRedirect($redirectTarget, 307);
                break;

            case XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT:
                $this->_response->setRedirect($redirectTarget, 301);
                break;

            default:
                throw new XenForo_Exception('Unknown redirect type');
        }

        $this->_needsContainer = false;

        return '';
    }

    public function renderView(
        $viewName,
        array $params = array(),
        $templateName = '',
        XenForo_ControllerResponse_View $subView = null
    ) {
        bdApi_Data_Helper_Cors::addHeaders($this, $this->_response);
        $viewOutput = $this->renderViewObject($viewName, 'Json', $params, $templateName);

        if (is_array($viewOutput)) {
            return self::jsonEncodeForOutput($viewOutput);
        } else {
            if ($viewOutput === null) {
                return self::jsonEncodeForOutput($this->getDefaultOutputArray($viewName, $params, $templateName));
            } else {
                return $viewOutput;
            }
        }
    }

    public function getDefaultOutputArray($viewName, $params, $templateName)
    {
        return $params;
    }

    public static function jsonEncodeForOutput($input, $addDefaultParams = true)
    {
        if ($addDefaultParams) {
            self::_addDefaultParams($input);
        }

        return XenForo_ViewRenderer_Json::jsonEncodeForOutput($input, false);
    }

    public function renderViewObject($class, $responseType, array &$params = array(), &$templateName = '')
    {
        $return = parent::renderViewObject($class, $responseType, $params, $templateName);

        if ($return === null) {
            foreach (array_keys($params) as $paramKey) {
                if (substr($paramKey, 0, 1) === '_') {
                    // filter out internal params
                    unset($params[$paramKey]);
                }
            }
        }

        return $return;
    }

    protected static function _addDefaultParams(array &$params = array())
    {
        bdApi_Data_Helper_Core::addDefaultResponse($params);
    }
}
