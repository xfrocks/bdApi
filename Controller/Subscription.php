<?php

namespace Xfrocks\Api\Controller;

class Subscription extends AbstractController
{
    public function actionPostIndex()
    {
        $params = $this->params()
            ->define('hub_callback', 'str')
            ->define('hub_mode', 'str')
            ->define('hub_topic', 'str')
            ->define('hub_lease_seconds', 'str')
            ->define('client_id', 'str');

        $session = $this->session();
        $clientId = $session->getToken() ? $session->getToken()->client_id : null;
        $isSessionClientId = true;

        if (empty($clientId)) {
            $clientId = $params['client_id'];
            $isSessionClientId = false;
        }

        if (empty($clientId)) {
            return $this->noPermission();
        }

        $validator = $this->app->validator('XF:Url');
        if (!$validator->isValid($params['hub_callback'])) {
            return $this->error(\XF::phrase('bdapi_subscription_callback_is_required'), 400);
        }

        if (!in_array($params['hub_mode'], [
            'subscribe',
            'unsubscribe'
        ], true)) {
            return $this->error(\XF::phrase('bdapi_subscription_mode_must_valid'), 400);
        }

        /** @var \Xfrocks\Api\Entity\Subscription[]|null $existingSubscriptions */
        $existingSubscriptions = null;
        $hubTopic = $params['hub_topic'];

        if ($params['hub_mode'] === 'subscribe') {
            if (!$isSessionClientId) {
                return $this->noPermission();
            }

            if (!$this->subscriptionRepo()->isValidTopic($hubTopic)) {
                return $this->error(\XF::phrase('bdapi_subscription_topic_not_recognized'));
            }
        } else {
            $existingSubscriptions = $this->finder('Xfrocks\Api:Subscription')
                ->where('client_id', $clientId)
                ->where('topic', $hubTopic)
                ->fetch();

            if ($existingSubscriptions->count() > 0) {
                foreach ($existingSubscriptions->keys() as $key) {
                    if ($existingSubscriptions[$key]['callback'] != $params['hub_callback']) {
                        unset($existingSubscriptions[$key]);
                    }
                }
            }
        }

        $verified = $this->subscriptionRepo()->verifyIntentOfSubscriber(
            $params['hub_callback'],
            $params['hub_mode'],
            $hubTopic,
            $params['hub_lease_seconds'],
            ['client_id' => $clientId]
        );

        if ($verified) {
            switch ($params['hub_mode']) {
                case 'unsubscribe':
                    foreach ($existingSubscriptions as $subscription) {
                        $subscription->delete();
                    }

                    $this->subscriptionRepo()->updateCallbacksForTopic($hubTopic);
                    break;
                default:
                    $subscriptions = $this->finder('Xfrocks\Api:Subscription')
                        ->where('client_id', $clientId)
                        ->where('topic', $hubTopic)
                        ->fetch();

                    /** @var \Xfrocks\Api\Entity\Subscription $subscription */
                    foreach ($subscriptions as $subscription) {
                        if ($subscription->callback == $params['hub_callback']) {
                            $subscription->delete();
                        }
                    }

                    /** @var \Xfrocks\Api\Entity\Subscription $subscriptionEntity */
                    $subscriptionEntity = $this->em()->create('Xfrocks\Api:Subscription');
                    $subscriptionEntity->client_id = $clientId;
                    $subscriptionEntity->callback = $params['hub_callback'];
                    $subscriptionEntity->topic = $hubTopic;

                    if ($params['hub_lease_seconds'] > 0) {
                        $subscriptionEntity->expire_date = \XF::$time + $params['hub_lease_seconds'];
                    }
                    
                    $subscriptionEntity->save();
            }
        }

        return $this->error(\XF::phrase('bdapi_subscription_cannot_verify_intent_of_subscriber'));
    }

    /**
     * @return \Xfrocks\Api\Repository\Subscription
     */
    protected function subscriptionRepo()
    {
        return $this->repository('Xfrocks\Api:Subscription');
    }
}