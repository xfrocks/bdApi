<?php

class bdApi_Extend_Visitor extends XFCP_bdApi_Extend_Visitor
{
    public function setVisitorLanguage($languageId)
    {
        if ($this->get('user_id') > 0) {
            // because XenForo ignore session language id if user is logged in
            // we have to override its value here
            // NOTE: the load_class for XenForo_Visitor requires 1.2.0+
            $session = bdApi_Data_Helper_Core::safeGetSession();
            $requestLanguageId = $session->get('languageId');
            if ($requestLanguageId > 0) {
                $languageId = $requestLanguageId;
            }
        }

        return parent::setVisitorLanguage($languageId);
    }

}