<?php

class bdApi_ViewApi_Page_Single extends bdApi_ViewApi_Base
{
    public function prepareParams()
    {
        if (empty($this->_params['_pageTemplate'])
            && !empty($this->_params['page'])
            && !empty($this->_params['_pageTemplateTitle'])
        ) {
            $this->_params['page']['page_html'] = $this->createTemplateObject(
                $this->_params['_pageTemplateTitle'],
                $this->_params
            );

            $this->_params['_pageTemplate'] = true;
        }

        parent::prepareParams();
    }
}
