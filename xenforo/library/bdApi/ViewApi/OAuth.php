<?php

class bdApi_ViewApi_OAuth extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (!empty($this->_params['_oauth2ResponseHttpHeaders'])) {
            foreach ($this->_params['_oauth2ResponseHttpHeaders'] as $headerName => $headerValue) {
                $this->_response->setHeader($headerName, $headerValue);
            }
        }
    }
}
