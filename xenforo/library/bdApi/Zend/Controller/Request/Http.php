<?php

class bdApi_Zend_Controller_Request_Http extends Zend_Controller_Request_Http
{
    protected $_method = 'GET';
    protected $_paramSources = array();

    public function setMethod($method)
    {
        if (in_array($method, array(
            'DELETE',
            'GET',
            'POST',
            'PUT',
        ))) {
            $this->_method = $method;
            return true;
        }

        return false;
    }

    public function getMethod()
    {
        return $this->_method;
    }
}
