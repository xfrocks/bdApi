<?php

class bdApi_AlertHandler_Ping extends XenForo_AlertHandler_Abstract
{
    public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
    {
        $contents = array();

        foreach ($contentIds as $contentId) {
            $contents[$contentId] = array();
        }

        return $contents;
    }

    protected function _getDefaultTemplateTitle($contentType, $action)
    {
        return 'bdapi_alert_ping_' . $action;
    }
}
