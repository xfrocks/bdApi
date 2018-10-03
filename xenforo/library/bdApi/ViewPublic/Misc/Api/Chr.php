<?php

class bdApi_ViewPublic_Misc_Api_Chr extends XenForo_ViewPublic_Base
{
    public function renderHtml()
    {
        $this->_renderer->setNeedsContainer(false);

        $template = $this->createTemplateObject($this->_templateName, $this->_params);
        $output = $template->render();

        foreach ($this->_params['required'] as $type => $requirements) {
            foreach ($requirements as $requirement) {
                $template->addRequiredExternal($type, $requirement);
            }
        }

        return $this->_renderer->replaceRequiredExternalPlaceholders($template, $output);
    }
}
