<?php

class bdApi_ViewAdmin_Token_Add extends XenForo_ViewAdmin_Base
{
    public function renderHtml()
    {
        if (isset($this->_params['scopes'])) {
            $scopes = array();

            foreach ($this->_params['scopes'] as $scope) {
                $scopes[$scope] = XenForo_Template_Helper_Core::callHelper('api_scopeGetText', array($scope));
            }

            $this->_params['scopes'] = $scopes;
        }
    }

}
