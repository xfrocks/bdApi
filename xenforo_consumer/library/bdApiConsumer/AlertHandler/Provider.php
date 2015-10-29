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
        $item['extra_data'] = unserialize($item['extra_data']);

        return $item;
    }

}
