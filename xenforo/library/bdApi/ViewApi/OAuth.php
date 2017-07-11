<?php

class bdApi_ViewApi_OAuth extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        parent::prepareParams();

        if (!empty($this->_params['_statusCode'])) {
            $this->_response->setHttpResponseCode($this->_params['_statusCode']);
        }

        if (!empty($this->_params['_headers'])) {
            foreach ($this->_params['_headers'] as $headerName => $headerValue) {
                $this->_response->setHeader($headerName, $headerValue);
            }
        }
    }
}
