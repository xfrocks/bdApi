<?php

class bdApi_ViewAdmin_RefreshToken_List extends XenForo_ViewAdmin_Base
{
    public function renderJson()
    {
        if (!empty($this->_params['filterView'])) {
            $this->_templateName = 'bdapi_refresh_token_list_items';
        }

        return null;
    }

}
