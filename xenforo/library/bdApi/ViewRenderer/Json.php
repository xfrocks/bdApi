<?php

class bdApi_ViewRenderer_Json extends XenForo_ViewRenderer_Json
{
    public function renderError($error)
    {
        if (!is_array($error)) {
            $error = array($error);
        }

        return self::jsonEncodeForOutput(array('errors' => $error));
    }

    public function renderMessage($message)
    {
        return self::jsonEncodeForOutput(array(
            'status' => 'ok',
            'message' => $message
        ));
    }

    public function renderRedirect($redirectType, $redirectTarget, $redirectMessage = null, array $redirectParams = array())
    {
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

    public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
    {
        $viewOutput = $this->renderViewObject($viewName, 'Json', $params, $templateName);

        if (is_array($viewOutput)) {
            return self::jsonEncodeForOutput($viewOutput);
        } else
            if ($viewOutput === null) {
                return self::jsonEncodeForOutput($this->getDefaultOutputArray($viewName, $params, $templateName));
            } else {
                return $viewOutput;
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

        foreach (array_keys($input) as $inputKey) {
            if (strpos($inputKey, '_') === 0) {
                // filter out internal params
                unset($input[$inputKey]);
            }
        }

        return XenForo_ViewRenderer_Json::jsonEncodeForOutput($input, false);
    }

    protected static function _addDefaultParams(array &$params = array())
    {
        bdApi_Data_Helper_Core::addDefaultResponse($params);
    }

}
