<?php

class bdApi_Deferred_UpdateSubscriptionCallback extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'batch' => 100,
            'position' => 0,

            'from' => '',
            'to' => '',
        ), $data);

        if (empty($data['from']) || empty($data['to']) || $data['to'] === $data['from']) {
            return false;
        }

        $db = XenForo_Application::getDb();
        $subscriptions = $db->fetchAll($db->limit('
            SELECT *
            FROM xf_bdapi_subscription
            WHERE callback = ?
            ORDER BY subscription_id
        ', $data['batch']), $data['from']);
        if (count($subscriptions) === 0) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            $data['position'] = $subscription['subscription_id'];

            $dw = XenForo_DataWriter::create('bdApi_DataWriter_Subscription');
            $dw->setExistingData($subscription, true);
            $dw->set('callback', $data['to']);
            $dw->save();

            if (defined('DEFERRED_CMD')) {
                echo(sprintf("Updated #%d (topic=%s)\n", $subscription['subscription_id'], $subscription['topic']));
            }
        }

        $actionPhrase = new XenForo_Phrase('updating');
        $typePhrase = new XenForo_Phrase('bdapi_subscriptions');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }
}
