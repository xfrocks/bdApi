<?php

namespace Xfrocks\Api\Repository;

use GuzzleHttp\Exception\ClientException;
use XF\Entity\ConversationMessage;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Repository;
use XF\Util\Php;
use Xfrocks\Api\Listener;
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

    /**
     * @return array
     */
    public function getClientSubscriptionsData()
    {
        $data = $this->app()->registry()->get(self::TYPE_CLIENT_DATA_REGISTRY);

        if (!is_array($data)) {
            $data = array();
        }

        return $data;
    }

    /**
     * @param string $type
     * @param int|string $id
     * @return int|null
     */
    public function deleteSubscriptionsForTopic($type, $id)
    {
        $topic = self::getTopic($type, $id);
        $deleted = $this->db()->delete('xf_bdapi_subscription', 'topic = ?', $topic);

        return $deleted;
    }

    /**
     * @param string $topic
     * @return void
     */
    public function updateCallbacksForTopic($topic)
    {
        list($type, $id) = self::parseTopic($topic);

        /** @var \Xfrocks\Api\Finder\Subscription $finder */
        $finder = $this->finder('Xfrocks\Api:Subscription');
        $finder->active();
        $finder->where('topic', $topic);

        $apiRouter = $this->app()->router(Listener::$routerType);

        $subscriptions = [];
        /** @var \Xfrocks\Api\Entity\Subscription $subscription */
        foreach ($finder->fetch() as $subscription) {
            $subscriptions[$subscription->subscription_id] = $subscription->toArray();
        }

        switch ($type) {
            case self::TYPE_NOTIFICATION:
                if (count($subscriptions) > 0) {
                    $userOption = [
                        'topic' => $topic,
                        'link' => $apiRouter->buildLink('notifications', null, ['oauth_token' => '']),
                        'subscriptions' => $subscriptions,
                    ];
                } else {
                    $userOption = [];
                }

                $this->db()->update(
                    'xf_user_option',
                    [self::getSubColumn($type) => serialize($userOption)],
                    'user_id = ?',
                    $id
                );
                break;
            case self::TYPE_THREAD_POST:
                if (count($subscriptions) > 0) {
                    $threadOption = [
                        'topic' => $topic,
                        'link' => $apiRouter->buildLink('posts', null, ['thread_id' => $id, 'oauth_token' => '']),
                        'subscriptions' => $subscriptions,
                    ];
                } else {
                    $threadOption = [];
                }

                $this->db()->update(
                    'xf_thread',
                    [self::getSubColumn($type) => serialize($threadOption)],
                    'thread_id = ?',
                    $id
                );
                break;
            case self::TYPE_USER:
                if (count($subscriptions) > 0) {
                    $userOption = [
                        'topic' => $topic,
                        'link' => $apiRouter->buildLink('users', ['user_id' => $id], ['oauth_token' => '']),
                        'subscriptions' => $subscriptions,
                    ];
                } else {
                    $userOption = [];
                }

                if ($id > 0) {
                    $this->db()->update(
                        'xf_user_option',
                        [$this->options()->bdApi_subscriptionColumnUser => serialize($userOption)],
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
                if (count($subscriptions) > 0) {
                    $data = [
                        'topic' => $topic,
                        'link' => '',
                        'subscriptions' => $subscriptions,
                    ];
                } else {
                    $data = [];
                }

                $this->app()->registry()->set(self::TYPE_CLIENT_DATA_REGISTRY, $data);
                break;
        }
    }

    /**
     * @param string $callback
     * @param string $mode
     * @param string $topic
     * @param int $leaseSeconds
     * @param array $extraParams
     * @return bool
     */
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
            $uri = $callback;
            foreach ($requestData as $key => $value) {
                $uri .= sprintf('%s%s=%s', strpos($uri, '?') === false ? '?' : '&', $key, rawurlencode($value));
            }
            $response = $client->get($uri);

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

    /**
     * @param string $topic
     * @param User|null $user
     * @return bool
     */
    public function isValidTopic(&$topic, User $user = null)
    {
        list($type, $id) = self::parseTopic($topic);

        if ($type != self::TYPE_CLIENT && !self::getSubOption($type)) {
            // subscription for this topic type has been disabled
            return false;
        }

        $user = $user ?: \XF::visitor();
        /** @var \Xfrocks\Api\XF\ApiOnly\Session\Session $session */
        $session = \XF::app()->session();
        $token = $session->getToken();
        $client = $token ? $token->Client : null;

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

                if ($id === '0' && $client) {
                    if (!isset($client->options['allow_user_0_subscription']) ||
                        $client->options['allow_user_0_subscription'] < 1
                    ) {
                        return false;
                    }
                }

                return (intval($id) === intval($user->user_id));
            case self::TYPE_CLIENT:
                return $client !== null;
        }

        return false;
    }

    /**
     * @param array $params
     * @param string $topicType
     * @param int|string $topicId
     * @param string $selfLink
     * @param array|string $subscriptionOption
     * @return bool
     */
    public function prepareDiscoveryParams(array &$params, $topicType, $topicId, $selfLink, $subscriptionOption)
    {
        if (!self::getSubOption($topicType)) {
            return false;
        }

        $response = $this->app()->response();

        $hubLink = $this->app()->router(Listener::$routerType)->buildLink('subscriptions', null, [
            'hub.topic' => self::getTopic($topicType, $topicId),
            'oauth_token' => ''
        ]);

        $response->header('Link', sprintf('<%s>; rel=hub', $hubLink), false);
        if ($selfLink !== '') {
            $response->header('Link', sprintf('<%s>; rel=self', $selfLink), false);
        }

        /** @var Session $session */
        $session = $this->app()->session();
        $token = $session->getToken();
        $clientId = $token ? $token->client_id : '';
        if (is_string($subscriptionOption)) {
            $subscriptionOption = Php::safeUnserialize($subscriptionOption);
        }
        if (is_array($subscriptionOption)
            && $clientId !== ''
            && isset($subscriptionOption['subscriptions'])
        ) {
            foreach ($subscriptionOption['subscriptions'] as $subscription) {
                if ($subscription['client_id'] == $clientId) {
                    $params['subscription_callback'] = $subscription['callback'];
                }
            }
        }

        return true;
    }

    /**
     * @param array $option
     * @param string $action
     * @param string $objectType
     * @param mixed $objectData
     * @return bool
     */
    public function ping(array $option, $action, $objectType, $objectData)
    {
        if (!isset($option['topic']) || !isset($option['subscriptions'])) {
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

            if (isset($option['link'])) {
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

    /**
     * @param string $action
     * @param ConversationMessage $message
     * @param User $alertedUser
     * @param User|null $triggerUser
     * @return bool
     */
    public function pingConversationMessage(
        $action,
        ConversationMessage $message,
        User $alertedUser,
        User $triggerUser = null
    ) {
        $type = self::TYPE_NOTIFICATION;
        if ($this->options()->bdApi_userNotificationConversation < 1 || !self::getSubOption($type)) {
            return false;
        }

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

        /** @var array $userOptionValue */
        $userOptionValue = $userOption->getValue(self::getSubColumn($type));
        if (count($userOptionValue) === 0) {
            return false;
        }

        return $this->ping($userOptionValue, $action, $type, $fakeAlert);
    }

    /**
     * @param string $action
     * @param Post $post
     * @return bool
     */
    public function pingThreadPost($action, Post $post)
    {
        $type = self::TYPE_THREAD_POST;
        if (!self::getSubOption($type)) {
            return false;
        }

        $thread = $post->Thread;
        if (!$thread) {
            return false;
        }

        /** @var array|null $threadOptionValue */
        $threadOptionValue = $thread->getValue(self::getSubColumn($type));
        if ($threadOptionValue === null || count($threadOptionValue) === 0) {
            return false;
        }

        return $this->ping($threadOptionValue, $action, $type, $post->post_id);
    }

    /**
     * @param string $action
     * @param User $user
     * @return bool
     */
    public function pingUser($action, User $user)
    {
        $type = self::TYPE_USER;
        if (!self::getSubOption($type)) {
            return false;
        }

        if ($action === 'insert') {
            $user0Option = $this->app()->simpleCache()->getValue('Xfrocks/Api', self::TYPE_USER_0_SIMPLE_CACHE);
            if (is_array($user0Option) && count($user0Option) > 0) {
                $this->ping($user0Option, $action, $type, $user->user_id);
            }
        }

        $userOption = $user->Option;
        if (!$userOption) {
            return false;
        }

        /** @var array $userOptionValue */
        $userOptionValue = $userOption->getValue(self::getSubColumn($type));
        if (count($userOptionValue) === 0) {
            return false;
        }

        return $this->ping($userOptionValue, $action, $type, $user->user_id);
    }

    /**
     * @param string $objectType
     * @param array $pingDataMany
     * @return array
     */
    public function preparePingDataMany($objectType, array $pingDataMany)
    {
        if (!self::getSubOption($objectType)) {
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

    /**
     * @param array $pingDataMany
     * @return array
     */
    protected function preparePingDataManyNotification(array $pingDataMany)
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

                if (isset($alertRaw['extra_data'])) {
                    $fakeAlert->extra_data = is_array($alertRaw['extra_data'])
                        ? $alertRaw['extra_data']
                        : Php::safeUnserialize($alertRaw['extra_data']);
                }

                $fakeAlert->setReadOnly(true);

                $alerts[$fakeAlertId] = $fakeAlert;
                $pingDataRef['object_data'] = $fakeAlertId;
            }
        }

        if (count($alertIds) > 0) {
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

            if (!isset($pingDataRef['object_data'])) {
                // no alert is attached to object data
                continue;
            }
            $alertId = $pingDataRef['object_data'];

            if (!isset($alerts[$alertId])) {
                // alert not found
                unset($pingDataMany[$pingDataKey]);
                continue;
            }
            $alertRef =& $alerts[$alertId];

            if ($alertRef instanceof UserAlert) {
                $transformContext = new TransformContext();
                /** @var Transformer $transformer */
                $transformer = $this->app()->container('api.transformer');

                $visitor = \XF::visitor();
                if ($visitor->user_id < 1 && isset($alertRef['alerted_user_id']) && $alertRef['alerted_user_id'] > 0) {
                    $visitor = $this->em->find('XF:User', $alertRef['alerted_user_id']);
                }

                try {
                    $pingDataRef['object_data'] = \XF::asVisitor(
                        $visitor,
                        function () use ($transformer, $transformContext, $alertRef) {
                            return $transformer->transformEntity($transformContext, null, $alertRef);
                        }
                    );
                } catch (\Exception $e) {
                    $pingDataRef['object_data'] = [];
                }
            }

            if (!is_numeric($alertRef['alert_id']) && isset($alertRef['extra_data']['object_data'])) {
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

    /**
     * @param array $userIds
     * @return array
     */
    protected function preparePingDataGetViewingUsers(array $userIds)
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

        if (count($dbUserIds) > 0) {
            $dbUsers = $this->em->findByIds('XF:User', $dbUserIds, [
                'Option',
                'Profile',
                'PermissionCombination',
                'Privacy'
            ]);

            foreach ($dbUsers as $user) {
                $allUsers[$user['user_id']] = $user;
                $users[$user['user_id']] = $user;
            }
        }

        return $users;
    }

    /**
     * @param array $pingDataMany
     * @return array
     */
    protected function preparePingDataManyPost(array $pingDataMany)
    {
        // TODO: do anything here?
        return $pingDataMany;
    }

    /**
     * @param array $pingDataMany
     * @return array
     */
    protected function preparePingDataManyUser(array $pingDataMany)
    {
        // TODO: do anything here?
        return $pingDataMany;
    }

    /**
     * @param string $type
     * @param int|string $id
     * @return string
     */
    public static function getTopic($type, $id)
    {
        return sprintf('%s_%s', $type, $id);
    }

    /**
     * @param string $topic
     * @return array
     */
    public static function parseTopic($topic)
    {
        if ($topic === '') {
            return array(self::TYPE_CLIENT, 0);
        }

        $parts = explode('_', $topic);
        $id = array_pop($parts);
        $type = implode('_', $parts);

        return [$type, $id];
    }

    /**
     * @param string $topicType
     * @return bool
     */
    public static function getSubOption($topicType)
    {
        $options = \XF::options();
        $topicTypeCamelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $topicType)));
        $optionKey = 'bdApi_subscription' . $topicTypeCamelCase;

        if (!$options->offsetExists($optionKey)) {
            return false;
        }

        return intval($options->offsetGet($optionKey)) > 0;
    }

    /**
     * @param string $topicType
     * @return string
     */
    public static function getSubColumn($topicType)
    {
        $options = \XF::options();
        $topicTypeCamelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $topicType)));
        $optionKey = 'bdApi_subscriptionColumn' . $topicTypeCamelCase;

        if (!$options->offsetExists($optionKey)) {
            return '';
        }

        return strval($options->offsetGet($optionKey));
    }
}
