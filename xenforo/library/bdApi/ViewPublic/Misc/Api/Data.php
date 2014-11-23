<?php

class bdApi_ViewPublic_Misc_Api_Data extends XenForo_ViewPublic_Base
{
    public function renderRaw()
    {
        bdApi_Data_Helper_Cors::addHeaders($this->_renderer, $this->_response);

        if (!empty($this->_params['callback'])) {
            $this->_response->setHeader('Content-Type', 'application/x-javascript; charset=UTF-8', true);
            return sprintf('%s(%s);', $this->_params['callback'], json_encode($this->_params['data']));
        } else {
            $this->_response->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
            return json_encode($this->_params['data']);
        }
    }

}
