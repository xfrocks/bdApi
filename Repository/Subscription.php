<?php

namespace Xfrocks\Api\Repository;

use GuzzleHttp\Exception\ClientException;
use XF\Entity\ConversationMessage;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Entity\UserAlert;
use XF\Entity\UserOption;
use XF\Mvc\Entity\Repository;
use XF\Util\Php;
use Xfrocks\Api\Transform\TransformContext;
use Xfrocks\Api\Transformer;
use Xfrocks\Api\XF\ApiOnly\Session\Session;

class Subscription extends Repository
{
    const TYPE_NOTIFICATION = 'user_notification';
    const TYPE_THREAD_POST = 'thread_post';

    const TYPE_USER = 'user';
    const TYPE_USER_0_SIMPLE_CACHE = 'apiUser0';

    const TYPE_CLIENT = '__client__';
    const TYPE_CLIENT_DATA_REGISTRY = 'apiSubs';

    public function getClientSubscriptionsData()
    {
        $data = $this->app()->registry()->get(self::TYPE_CLIENT_DATA_REGISTRY);

        if (empty($data)) {
            $data = array();
        }

        return $data;
    }

    public function deleteSubscriptionsForTopic($type, $id)
    {
        $topic = self::getTopic($type, $id);
        $deleted = $this->db()->delete('xf_bdapi_subscription', 'topic = ?', $topic);

        return $deleted;
    }

    public function updateCallbacksForTopic($topic)
    {
        list($type, $id) = self::parseTopic($topic);

        /** @var \Xfrocks\Api\Finder\Subscription $finder */
        $finder = $this->finder('Xfrocks\Api:Subscription');
        $finder->active();
        $finder->where('topic', $topic);

        $subscriptions = [];
        /** @var \Xfrocks\Api\Entity\Subscription $subscription */
        foreach ($finder->fetch() as $subscription) {
            $subscriptions[$subscription->subscription_id] = $subscription->toArray();
        }

        switch ($type) {
            case self::TYPE_NOTIFICATION:
                if (!empty($subscriptions)) {
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

        try {
            $response = $client->get($callback, [
                'query' => $requestData
            ]);

            $body = trim($response->getBody()->getContents());
            $httpCode = $response->getStatusCode();
        } catch (ClientException $e) {
            $body = $e->getMessage();
            $httpCode = 500;
        }

        if (\XF::$debugMode) {
            /** @var Log $logRepo */
            $logRepo = $this->repository('Xfrocks\Api:Log');
            $logRepo->logRequest(
                'GET',
                $callback,
                $requestData,
                $httpCode,
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

        if ($httpCode < 200 or $httpCode > 299) {
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
        $client = null;
        if ($token) {
            $client = $token->Client;
        }

        switch ($type) {
            case self::TYPE_NOTIFICATION:
                if ($id === 'me') {
                    // now supports user_notification_me
                    $id = $user->user_id;
                    $topic = self::getTopic($type, $id);
                }

                return (($id > 0) and ($id == $user->user_id));
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

        $response->header('Link', sprintf('<%s>; rel=hub', $hubLink), false);
        if (!empty($selfLink)) {
            $response->header('Link', sprintf('<%s>; rel=self', $selfLink), false);
        }

        if (!empty($subscriptionOption)) {
            if (is_string($subscriptionOption)) {
                $subscriptionOption = Php::safeUnserialize($subscriptionOption);
            }

            /** @var Session $session */
            $session = $this->app()->session();
            $token = $session->getToken();

            $clientId = '';

            if ($token) {
                $clientId = $token->client_id;
            }

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

    public function ping(array $option, $action, $objectType, $objectData)
    {
        if (!isset($option['topic'])
            || empty($option['subscriptions'])
        ) {
            return false;
        }

        $pingedClientIds = array();

        foreach ($option['subscriptions'] as $subscription) {
            if ($subscription['expire_date'] > 0
                && $subscription['expire_date'] < \XF::$time
            ) {
                // expired
                continue;
            }

            if (in_array($subscription['client_id'], $pingedClientIds, true)) {
                // duplicated subscription
                continue;
            }
            $pingedClientIds[] = $subscription['client_id'];

            $pingData = array(
                'client_id' => $subscription['client_id'],
                'topic' => $option['topic'],
                'action' => $action,
                'object_data' => $objectData,
            );

            if (!empty($option['link'])) {
                $pingData['link'] = $option['link'];
            }

            /** @var \Xfrocks\Api\Repository\PingQueue $pingQueueRepo */
            $pingQueueRepo = $this->repository('Xfrocks\Api:PingQueue');
            $pingQueueRepo->insertQueue(
                $subscription['callback'],
                $objectType,
                $pingData,
                $subscription['expire_date']
            );
        }

        return true;
    }

    public function pingConversationMessage($action, ConversationMessage $message, User $alertedUser, User $triggerUser = null)
    {
        if (!$this->options()->bdApi_userNotificationConversation
            || !static::getSubscription(self::TYPE_NOTIFICATION)
        ) {
            return false;
        }

        $subColumn = $this->options()->bdApi_subscriptionColumnUserNotification;
        /** @var UserOption|null $userOption */
        $userOption = $alertedUser->Option;
        if (!$userOption) {
            return false;
        }

        if ($action === 'reply') {
            // XF has removed reply alert. So use own action to prevent conflict
            $action = 'bdapi_reply';
        }

        $templater = $this->app()->templater();

        $triggerUser = $triggerUser ?: $message->User;

        $extraData = [
            'object_data' => [
                'notification_id' => 0,
                'notification_html' => ''
            ]
        ];

        $extraData['object_data']['message'] = [
            'conversation_id' => $message->conversation_id,
            'title' => $message->Conversation->title,
            'message_id' => $message->message_id,
            'message' => $templater->fn('snippet', [$message->message, 140, ['stripQuote' => true]])
        ];

        $fakeAlert = [
            'alert_id' => 0,
            'alerted_user_id' => $alertedUser->user_id,
            'user_id' => $triggerUser->user_id,
            'username' => $triggerUser->username,
            'content_type' => 'conversation_message',
            'content_id' => $message->message_id,
            'action' => $action,
            'event_date' => \XF::$time,
            'view_date' => 0,
            'extra_data' => serialize($extraData)
        ];

        $userOptionValue = $userOption->getValue($subColumn);
        if (!empty($userOptionValue)) {
            $this->ping($userOptionValue, $action, self::TYPE_NOTIFICATION, $fakeAlert);
        }

        return true;
    }

    public function pingThreadPost($action, Post $post)
    {
        if (!self::getSubscription(self::TYPE_THREAD_POST)) {
            return false;
        }

        $subColumn = $this->options()->bdApi_subscriptionColumnThreadPost;
        if (!empty($post->Thread->getValue($subColumn))) {
            $option = $post->Thread->getValue($subColumn);
            $this->ping(
                $option,
                $action,
                self::TYPE_THREAD_POST,
                $post->post_id
            );
        }

        return true;
    }

    public function pingUser($action, User $user)
    {
        if (!self::getSubscription(self::TYPE_USER)) {
            return false;
        }

        if ($action === 'insert') {
            $user0Option = $this->app()->simpleCache()->getValue('Xfrocks/Api', self::TYPE_USER_0_SIMPLE_CACHE);
            if (!empty($user0Option)) {
                $this->ping(
                    $user0Option,
                    $action,
                    self::TYPE_USER,
                    $user->user_id
                );
            }
        }

        $subColumn = $this->options()->bdApi_subscriptionColumnUser;
        /** @var UserOption|null $userOption */
        $userOption = $user->Option;
        if (!$userOption) {
            return false;
        }

        $userOptionValue = $userOption->getValue($subColumn);
        if (!empty($userOptionValue)) {
            $this->ping(
                $userOptionValue,
                $action,
                self::TYPE_USER,
                $user->user_id
            );
        }

        return true;
    }

    public function preparePingDataMany($objectType, array $pingDataMany)
    {
        if (!self::getSubscription($objectType)) {
            // subscription for this topic type has been disabled
            return array();
        }

        switch ($objectType) {
            case self::TYPE_NOTIFICATION:
                return $this->preparePingDataManyNotification($pingDataMany);
            case self::TYPE_THREAD_POST:
                return $this->preparePingDataManyPost($pingDataMany);
            case self::TYPE_USER:
                return $this->preparePingDataManyUser($pingDataMany);
        }

        return array();
    }

    protected function preparePingDataManyNotification($pingDataMany)
    {
        $alertIds = array();
        $alerts = array();
        foreach ($pingDataMany as $key => &$pingDataRef) {
            if (is_numeric($pingDataRef['object_data'])) {
                $alertIds[] = $pingDataRef['object_data'];
            } elseif (is_array($pingDataRef['object_data'])
                && isset($pingDataRef['object_data']['alert_id'])
                && $pingDataRef['object_data']['alert_id'] == 0
            ) {
                $fakeAlertId = sprintf(md5($key));
                $pingDataRef['object_data']['alert_id'] = $fakeAlertId;
                $alertRaw = $pingDataRef['object_data'];

                /** @var UserAlert $fakeAlert */
                $fakeAlert = $this->em->create('XF:UserAlert');
                $fakeAlert->alerted_user_id = $alertRaw['alerted_user_id'];
                $fakeAlert->user_id = $alertRaw['user_id'];
                $fakeAlert->username = $alertRaw['username'];
                $fakeAlert->content_type = $alertRaw['content_type'];
                $fakeAlert->content_id = $alertRaw['content_id'];
                $fakeAlert->action = $alertRaw['action'];
                $fakeAlert->event_date = $alertRaw['event_date'];
                $fakeAlert->view_date = $alertRaw['view_date'];
                if (!empty($alertRaw['extra_data'])) {
                    $fakeAlert->extra_data = is_array($alertRaw['extra_data'])
                        ? $alertRaw['extra_data']
                        : unserialize($alertRaw['extra_data']);
                }

                $fakeAlert->setReadOnly(true);

                $alerts[$fakeAlertId] = $fakeAlert;
                $pingDataRef['object_data'] = $fakeAlertId;
            }
        }

        if (!empty($alertIds)) {
            $realAlerts = $this->em->findByIds('XF:UserAlert', $alertIds);
            foreach ($realAlerts as $alertId => $alert) {
                $alerts[$alertId] = $alert;
            }
        }

        $userIds = array();
        $alertsByUser = array();
        foreach ($alerts as $alert) {
            $userIds[] = $alert['alerted_user_id'];

            if (!isset($alertsByUser[$alert['alerted_user_id']])) {
                $alertsByUser[$alert['alerted_user_id']] = array();
            }
            $alertsByUser[$alert['alerted_user_id']][$alert['alert_id']] = $alert;
        }

        $viewingUsers = $this->preparePingDataGetViewingUsers($userIds);

        foreach ($alertsByUser as $userId => &$userAlerts) {
            if (!isset($viewingUsers[$userId])) {
                // user not found
                foreach (array_keys($userAlerts) as $userAlertId) {
                    // delete the alert too
                    unset($alerts[$userAlertId]);
                }
                continue;
            }

            foreach (array_keys($userAlerts) as $userAlertId) {
                $alerts[$userAlertId] = $userAlerts[$userAlertId];
            }
        }

        foreach (array_keys($pingDataMany) as $pingDataKey) {
            $pingDataRef = &$pingDataMany[$pingDataKey];

            if (empty($pingDataRef['object_data'])) {
                // no alert is attached to object data
                continue;
            }

            if (!isset($alerts[$pingDataRef['object_data']])) {
                // alert not found
                unset($pingDataMany[$pingDataKey]);
                continue;
            }
            $alertRef = &$alerts[$pingDataRef['object_data']];

            if ($alertRef instanceof UserAlert) {
                $transformContext = new TransformContext();
                /** @var Transformer $transformer */
                $transformer = $this->app()->container('api.transformer');

                $visitor = \XF::visitor();
                if (!$visitor->user_id && !empty($alertRef['alerted_user_id'])) {
                    $visitor = $this->em->find('XF:User', $alertRef['alerted_user_id']);
                }

                $pingDataRef['object_data'] = \XF::asVisitor($visitor, function () use ($transformer, $transformContext, $alertRef) {
                    return $transformer->transformEntity($transformContext, null, $alertRef);
                });
            }
            
            if (!is_numeric($alertRef['alert_id'])
                && !empty($alertRef['extra_data']['object_data'])
            ) {
                // fake alert, use the included object_data
                $pingDataRef['object_data'] = array_merge(
                    $pingDataRef['object_data'],
                    $alertRef['extra_data']['object_data']
                );
            }

            $alertedUserId = $alertRef['alerted_user_id'];
            if (isset($viewingUsers[$alertedUserId])) {
                $alertedUser = $viewingUsers[$alertedUserId];
                if (isset($alertedUser['alerts_unread'])) {
                    $pingDataRef['object_data']['user_unread_notification_count'] = $alertedUser['alerts_unread'];
                }
            }
        }

        return $pingDataMany;
    }

    protected function preparePingDataGetViewingUsers($userIds)
    {
        static $allUsers = array();
        $users = array();

        $dbUserIds = array();
        foreach ($userIds as $userId) {
            if ($userId == \XF::visitor()->user_id) {
                $users[$userId] = \XF::visitor();
            } elseif ($userId == 0) {
                /** @var \XF\Repository\User $userRepo */
                $userRepo = $this->repository('XF:User');
                $users[$userId] = $userRepo->getGuestUser();
            } elseif (isset($allUsers[$userId])) {
                $users[$userId] = $allUsers[$userId];
            } else {
                $dbUserIds[] = $userId;
            }
        }

        if (!empty($dbUserIds)) {
            $dbUsers = $this->em->findByIds('XF:User', $dbUserIds, [
                'Option', 'Profile', 'PermissionCombination', 'Privacy'
            ]);

            foreach ($dbUsers as $user) {
                $allUsers[$user['user_id']] = $user;
                $users[$user['user_id']] = $user;
            }
        }

        return $users;
    }

    protected function preparePingDataManyPost($pingDataMany)
    {
        // TODO: do anything here?
        return $pingDataMany;
    }

    protected function preparePingDataManyUser($pingDataMany)
    {
        // TODO: do anything here?
        return $pingDataMany;
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

        if (!\XF::options()->offsetExists('bdApi_subscription' . $optionKey)) {
            return null;
        }

        return \XF::options()->offsetGet('bdApi_subscription' . $optionKey);
    }
}
