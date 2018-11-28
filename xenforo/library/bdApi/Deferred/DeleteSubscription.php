<?php

class bdApi_Deferred_DeleteSubscription extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'batch' => 100,
            'position' => 0,
            'clientId' => '',
        ), $data);

        /* @var $subscriptionModel bdApi_Model_Subscription */
        $subscriptionModel = XenForo_Model::create('bdApi_Model_Subscription');

        if (empty($data['clientId'])) {
            return false;
        }

        $subscriptions = $subscriptionModel->getSubscriptions(array(
            'client_id' => $data['clientId'],
        ), array(
            'order' => 'subscription_id',
        ));
        if (count($subscriptions) === 0) {
            return false;
        }

        foreach ($subscriptions as $subscription) {
            $data['position'] = $subscription['subscription_id'];

            $dw = XenForo_DataWriter::create('bdApi_DataWriter_Subscription');
            if ($dw->setExistingData($subscription, true)) {
                $dw->delete();
            }
        }

        $actionPhrase = new XenForo_Phrase('deleting');
        $typePhrase = new XenForo_Phrase('bdapi_subscriptions');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }
}
