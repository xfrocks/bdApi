<?php

class bdApi_ViewApi_Batch_Index extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        $this->_params = bdApi_Data_Helper_Batch::prepareViewParams($this->_renderer, $this);
    }
}