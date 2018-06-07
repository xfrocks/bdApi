<?php

class bdApiConsumer_bdApi_Model_Subscription extends XFCP_bdApiConsumer_bdApi_Model_Subscription
{
    protected function _preparePingDataManyNotification($pingDataMany)
    {
        $pingDataMany = parent::_preparePingDataManyNotification($pingDataMany);

        foreach (array_keys($pingDataMany) as $key) {
            if (!empty($pingDataMany[$key]['object_data']['notification_type'])) {
                if (strpos($pingDataMany[$key]['object_data']['notification_type'], 'bdapi_consumer_') === 0) {
                    // do not push out pings of our own alerts
                    // may cause loop if a site installs both [bd] API and [bd] API Consumer
                    unset($pingDataMany[$key]);
                }
            }
        }

        return $pingDataMany;
    }
}
