<?php

class bdApi_ViewPublic_Misc_Api_Chr extends XenForo_ViewPublic_Base
{
    public function prepareParams()
    {
        $template = $this->createTemplateObject('');
        foreach ($this->_params['required'] as $type => $requirements) {
            foreach ($requirements as $requirement) {
                $template->addRequiredExternal($type, $requirement);
            }
        }
    }
}
