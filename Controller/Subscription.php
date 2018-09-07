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
        $token = $session->getToken();

        if ($token) {
            $clientId = $token->client_id;
        }

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
            return $this->responseError(\XF::phrase('bdapi_subscription_callback_is_required'));
        }

        if (!in_array($params['hub_mode'], [
            'subscribe',
            'unsubscribe'
        ], true)) {
            return $this->responseError(\XF::phrase('bdapi_subscription_mode_must_valid'));
        }

        /** @var \Xfrocks\Api\Entity\Subscription[]|null $existingSubscriptions */
        $existingSubscriptions = null;
        $hubTopic = $params['hub_topic'];

        if ($params['hub_mode'] === 'subscribe') {
            if (!$isSessionClientId) {
                return $this->noPermission();
            }

            if (!$this->subscriptionRepo()->isValidTopic($hubTopic)) {
                return $this->responseError(\XF::phrase('bdapi_subscription_topic_not_recognized'));
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

            $this->setResponseType('raw');
            return $this->view('Xfrocks\Api:Subscription\Post', 'DEFAULT', [
                'httpResponseCode' => 202
            ]);
        }

        return $this->responseError(\XF::phrase('bdapi_subscription_cannot_verify_intent_of_subscriber'));
    }

    protected function responseError($error)
    {
        $this->setResponseType('raw');

        return $this->view('Xfrocks\Api:Subscription\Post', 'DEFAULT', [
            'httpResponseCode' => 400,
            'message' => $error
        ]);
    }

    protected function getDefaultApiScopeForAction($action)
    {
        return null;
    }

    /**
     * @return \Xfrocks\Api\Repository\Subscription
     */
    protected function subscriptionRepo()
    {
        /** @var \Xfrocks\Api\Repository\Subscription $repo */
        $repo = $this->repository('Xfrocks\Api:Subscription');

        return $repo;
    }
}
