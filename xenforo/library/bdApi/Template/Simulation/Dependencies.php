<?php

class bdApi_Template_Simulation_Dependencies extends XenForo_Dependencies_Public
{
    protected $_noNameTemplate = null;

    public function createTemplateObject($templateName, array $params = array())
    {
        if ($templateName === '' && $this->_noNameTemplate !== null) {
            return $this->_noNameTemplate;
        }

        if ($params) {
            $params = XenForo_Application::mapMerge($this->_defaultTemplateParams, $params);
        } else {
            $params = $this->_defaultTemplateParams;
        }

        $template = new bdApi_Template_Simulation_Template($templateName, $params);

        if ($templateName === '') {
            $this->_noNameTemplate = $template;
        }

        return $template;
    }
}
