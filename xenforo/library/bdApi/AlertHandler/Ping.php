<?php

class bdApi_AlertHandler_Ping extends XenForo_AlertHandler_Abstract
{
    const ACTION_FAKE = 'fake';

    public function getContentByIds(array $contentIds, $model, $userId, array $viewingUser)
    {
        $contents = array();

        foreach ($contentIds as $contentId) {
            $contents[$contentId] = array();
        }

        return $contents;
    }

    public function renderHtml(array $item, XenForo_View $view)
    {
        if ($item['action'] === self::ACTION_FAKE) {
            return '';
        }

        return parent::renderHtml($item, $view);
    }

    protected function _getDefaultTemplateTitle($contentType, $action)
    {
        return 'bdapi_alert_ping_' . $action;
    }

    public static function fakeAlert($userId, array $pingData)
    {
        return array(
            'alert_id' => 0,
            'alerted_user_id' => $userId,
            'user_id' => $userId,
            'username' => '',
            'content_type' => 'api_ping',
            'content_id' => 0,
            'action' => self::ACTION_FAKE,
            'event_date' => XenForo_Application::$time,
            'view_date' => 0,
            'extra_data' => serialize(array('ping_data' => $pingData)),
        );
    }
}
