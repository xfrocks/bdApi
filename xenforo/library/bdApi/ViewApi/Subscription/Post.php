<?php

class bdApi_ViewApi_Subscription_Post extends bdApi_ViewApi_Base
{
    public function renderRaw()
    {
        if (!empty($this->_params['httpResponseCode'])) {
            $this->_response->setHttpResponseCode($this->_params['httpResponseCode']);
        }

        if (!empty($this->_params['message'])) {
            return $this->_params['message'];
        } else {
            return '';
        }
    }
}
