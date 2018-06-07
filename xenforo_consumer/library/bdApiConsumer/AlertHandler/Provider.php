<?php

class bdApiConsumer_AlertHandler_Provider extends XenForo_AlertHandler_Abstract
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
        return 'bdapi_consumer_alert_provider';
    }

    protected function _prepareAlertAfterAction(array $item, $content, array $viewingUser)
    {
        $provider = bdApiConsumer_Option::getProviderByCode($item['action']);
        if (!empty($provider)
            && !empty($item['extra']['notification']['notification_html'])
        ) {
            $item['notificationHtml'] = strip_tags($item['extra']['notification']['notification_html'], '<a>');
            $item['notificationProvider'] = $provider;
        }

        return $item;
    }
}
