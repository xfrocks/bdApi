<?php

namespace Xfrocks\Api\Repository;

use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\Repository;
use XF\Util\Php;
use Xfrocks\Api\XF\ApiOnly\Session\Session;

class Subscription extends Repository
{
    const TYPE_NOTIFICATION = 'user_notification';
    const TYPE_THREAD_POST = 'thread_post';

    const TYPE_USER = 'user';
    const TYPE_USER_0_SIMPLE_CACHE = 'apiUser0';

    const TYPE_CLIENT = '__client__';
    const TYPE_CLIENT_DATA_REGISTRY = 'apiSubs';

    public function updateCallbacksForTopic($topic)
    {
        list($type, $id) = self::parseTopic($topic);

        /** @var \Xfrocks\Api\Finder\Subscription $finder */
        $finder = $this->finder('Xfrocks\Api:Subscription');
        $finder->active();
        $finder->where('topic', $topic);

        $subscriptions = $finder->fetch();

        switch ($type) {
            case self::TYPE_NOTIFICATION:
                if ($subscriptions->count() > 0) {
                    $userOption = array(
                        'topic' => $topic,
                        'link' => $this->app()->router('api')->buildLink(
                            'notifications',
                            null,
                            array('oauth_token' => '')
                        ),
                        'subscriptions' => $subscriptions,
                    );
                } else {
                    $userOption = array();
                }

                $this->db()->update(
                    'xf_user_option',
                    array(
                        $this->options()->bdApi_subscriptionColumnUserNotification => serialize($userOption)
                    ),
                    'user_id = ?',
                    $id
                );
                break;
            case self::TYPE_THREAD_POST:
                if (!empty($subscriptions)) {
                    $threadOption = array(
                        'topic' => $topic,
                        'link' => $this->app()->router('api')->buildLink(
                            'posts',
                            null,
                            array(
                                'thread_id' => $id,
                                'oauth_token' => '',
                            )
                        ),
                        'subscriptions' => $subscriptions,
                    );
                } else {
                    $threadOption = array();
                }

                $this->db()->update(
                    'xf_thread',
                    array($this->options()->bdApi_subscriptionColumnThreadPost => serialize($threadOption)),
                    'thread_id = ?',
                    $id
                );
                break;
            case self::TYPE_USER:
                if (!empty($subscriptions)) {
                    $userOption = array(
                        'topic' => $topic,
                        'link' => $this->app()->router('api')->buildLink(
                            'users',
                            array('user_id' => $id),
                            array('oauth_token' => '')
                        ),
                        'subscriptions' => $subscriptions,
                    );
                } else {
                    $userOption = array();
                }

                if ($id > 0) {
                    $this->db()->update(
                        'xf_user_option',
                        array($this->options()->bdApi_subscriptionColumnUser => serialize($userOption)),
                        'user_id = ?',
                        $id
                    );
                } else {
                    $this->app()
                        ->simpleCache()
                        ->setValue('Xfrocks/Api', self::TYPE_USER_0_SIMPLE_CACHE, $userOption);
                }
                break;
            case self::TYPE_CLIENT:
                if (!empty($subscriptions)) {
                    $data = array(
                        'topic' => $topic,
                        'link' => '',
                        'subscriptions' => $subscriptions,
                    );
                } else {
                    $data = array();
                }

                $this->app()->registry()->set(self::TYPE_CLIENT_DATA_REGISTRY, $data);
                break;
        }
    }

    public function verifyIntentOfSubscriber($callback, $mode, $topic, $leaseSeconds, array $extraParams = [])
    {
        $challenge = md5(\XF::$time . $callback . $mode . $topic . $leaseSeconds);
        $challenge = md5($challenge . $this->app()->config('globalSalt'));

        $client = $this->app()->http()->client();

        $requestData = array_merge(array(
            'hub.mode' => $mode,
            'hub.topic' => $topic,
            'hub.lease_seconds' => $leaseSeconds,
            'hub.challenge' => $challenge,
        ), $extraParams);

        $response = $client->get($callback, [
            'query' => $requestData
        ]);

        $body = trim($response->getBody()->getContents());

        if (\XF::$debugMode) {
            /** @var Log $logRepo */
            $logRepo = $this->repository('Xfrocks\Api:Log');
            $logRepo->logRequest(
                'GET',
                $callback,
                $requestData,
                $response->getStatusCode(),
                array('message' => $body),
                array(
                    'client_id' => '',
                    'user_id' => 0,
                    'ip_address' => '127.0.0.1',
                )
            );
        }

        if ($body !== $challenge) {
            return false;
        }

        if ($response->getStatusCode() < 200 OR $response->getStatusCode() > 299) {
            return false;
        }

        return true;
    }

    public function isValidTopic(&$topic, User $user = null)
    {
        list($type, $id) = self::parseTopic($topic);

        if ($type != self::TYPE_CLIENT
            && !self::getSubscription($type)
        ) {
            // subscription for this topic type has been disabled
            return false;
        }

        $user = $user ?: \XF::visitor();
        /** @var \Xfrocks\Api\XF\ApiOnly\Session\Session $session */
        $session = \XF::app()->session();

        $token = $session->getToken();
        $client = $token->Client;

        switch ($type) {
            case self::TYPE_NOTIFICATION:
                if ($id === 'me') {
                    // now supports user_notification_me
                    $id = $user->user_id;
                    $topic = self::getTopic($type, $id);
                }

                return (($id > 0) AND ($id == $user->user_id));
            case self::TYPE_THREAD_POST:
                /** @var Thread|null $thread */
                $thread = $this->em->find('XF:Thread', $id);
                if (!$thread) {
                    return false;
                }

                return $thread->user_id == $user->user_id;
            case self::TYPE_USER:
                if ($id === 'me') {
                    // now supports user_me
                    $id = $user->user_id;
                    $topic = self::getTopic($type, $id);
                }

                if ($id === '0'
                    && $client
                    && !empty($client->options['allow_user_0_subscription'])
                ) {
                    return false;
                }

                return (intval($id) === intval($user->user_id));
            case self::TYPE_CLIENT:
                return !empty($client);
        }

        return false;
    }

    public function prepareDiscoveryParams(array &$params, $topicType, $topicId, $selfLink, $subscriptionOption)
    {
        if (!self::getSubscription($topicType)) {
            return false;
        }

        $response = $this->app()->response();

        $hubLink = $this->app()->router('api')->buildLink('subscriptions', null, [
            'hub.topic' => self::getTopic($topicType, $topicId),
            'oauth_token' => ''
        ]);

        $response->header('Link', sprintf('<%s>; rel=hub', $hubLink));
        if (!empty($selfLink)) {
            $response->header('Link', sprintf('<%s>; rel=self', $selfLink));
        }

        if (!empty($subscriptionOption)) {
            if (is_string($subscriptionOption)) {
                $subscriptionOption = Php::safeUnserialize($subscriptionOption);
            }

            /** @var Session $session */
            $session = $this->app()->session();
            $clientId = $session->getToken() ? $session->getToken()->client_id : '';

            if (is_array($subscriptionOption)
                && $clientId
                && !empty($subscriptionOption['subscriptions'])
            ) {
                foreach ($subscriptionOption['subscriptions'] as $subscription) {
                    if ($subscription['client_id'] == $clientId) {
                        $params['subscription_callback'] = $subscription['callback'];
                    }
                }
            }
        }

        return true;
    }

    public static function getTopic($type, $id)
    {
        return sprintf('%s_%s', $type, $id);
    }

    public static function parseTopic($topic)
    {
        if (empty($topic)) {
            return array(self::TYPE_CLIENT, 0);
        }

        $parts = explode('_', $topic);
        $id = array_pop($parts);
        $type = implode('_', $parts);

        return [$type, $id];
    }

    public static function getSubscription($topicType)
    {
        $optionKey = str_replace(' ', '', ucwords(str_replace('_', ' ', $topicType)));

        return \XF::options()->offsetGet('subscription' . $optionKey);
    }
}