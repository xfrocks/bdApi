<?php

class bdApi_Model_Subscription extends XenForo_Model
{
    const TYPE_NOTIFICATION = 'user_notification';
    const TYPE_THREAD_POST = 'thread_post';
    const TYPE_USER = 'user';

    // this is a special type, blank topic will be detect as this type
    const TYPE_CLIENT = '__client__';
    const TYPE_CLIENT_DATA_REGISTRY = 'apiSubs';

    const FETCH_CLIENT = 0x01;

    public function getClientSubscriptionsData()
    {
        $data = $this->_getDataRegistryModel()->get(self::TYPE_CLIENT_DATA_REGISTRY);

        if (empty($data)) {
            $data = array();
        }

        return $data;
    }

    public function updateCallbacksForTopic($topic)
    {
        list($type, $id) = self::parseTopic($topic);

        $subscriptions = $this->getSubscriptions(array(
            'topic' => $topic,
            'expired' => false,
        ));

        switch ($type) {
            case self::TYPE_NOTIFICATION:
                if (!empty($subscriptions)) {
                    $userOption = array(
                        'topic' => $topic,
                        'link' => bdApi_Data_Helper_Core::safeBuildApiLink(
                            'notifications',
                            null,
                            array('oauth_token' => '')
                        ),
                        'subscriptions' => $subscriptions,
                    );
                } else {
                    $userOption = array();
                }

                $this->_getDb()->update('xf_user_option', array('bdapi_user_notification' => serialize($userOption)), array('user_id = ?' => $id));
                break;
            case self::TYPE_THREAD_POST:
                if (!empty($subscriptions)) {
                    $threadOption = array(
                        'topic' => $topic,
                        'link' => bdApi_Data_Helper_Core::safeBuildApiLink(
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

                $this->_getDb()->update('xf_thread', array('bdapi_thread_post' => serialize($threadOption)), array('thread_id = ?' => $id));
                break;
            case self::TYPE_USER:
                if (!empty($subscriptions)) {
                    $userOption = array(
                        'topic' => $topic,
                        'link' => bdApi_Data_Helper_Core::safeBuildApiLink(
                            'users',
                            array('user_id' => $id),
                            array('oauth_token' => '')
                        ),
                        'subscriptions' => $subscriptions,
                    );
                } else {
                    $userOption = array();
                }

                $this->_getDb()->update('xf_user_option', array('bdapi_user' => serialize($userOption)), array('user_id = ?' => $id));
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

                $this->_getDataRegistryModel()->set(self::TYPE_CLIENT_DATA_REGISTRY, $data);
                break;
        }
    }

    public function ping(array $option, $action, $objectType, $objectData)
    {
        if (!isset($option['topic'])
            || empty($option['subscriptions'])
        ) {
            return false;
        }

        $pingedClientIds = array();

        /* @var $pingQueueModel bdApi_Model_PingQueue */
        $pingQueueModel = $this->getModelFromCache('bdApi_Model_PingQueue');

        foreach ($option['subscriptions'] as $subscription) {
            if ($subscription['expire_date'] > 0
                && $subscription['expire_date'] < XenForo_Application::$time
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

            $pingQueueModel->insertQueue($subscription['callback'], $objectType, $pingData, $subscription['expire_date']);
        }

        return true;
    }

    public function preparePingDataMany($objectType, array $pingDataMany)
    {
        if (!bdApi_Option::getSubscription($objectType)) {
            // subscription for this topic type has been disabled
            return array();
        }

        switch ($objectType) {
            case self::TYPE_NOTIFICATION:
                return $this->_preparePingDataManyNotification($pingDataMany);
            case self::TYPE_THREAD_POST:
                return $this->_preparePingDataManyPost($pingDataMany);
            case self::TYPE_USER:
                return $this->_preparePingDataManyUser($pingDataMany);
        }

        return array();
    }

    protected function _preparePingDataManyNotification($pingDataMany)
    {
        /* @var $alertModel bdApi_XenForo_Model_Alert */
        $alertModel = $this->getModelFromCache('XenForo_Model_Alert');

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
                $alerts[$fakeAlertId] = $pingDataRef['object_data'];
                $pingDataRef['object_data'] = $fakeAlertId;
            }
        }

        if (!empty($alertIds)) {
            $realAlerts = $alertModel->bdApi_getAlertsByIds($alertIds);
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

        $viewingUsers = $this->_preparePingData_getViewingUsers($userIds);
        foreach ($alertsByUser as $userId => &$userAlerts) {
            if (!isset($viewingUsers[$userId])) {
                // user not found
                foreach (array_keys($userAlerts) as $userAlertId) {
                    // delete the alert too
                    unset($alerts[$userAlertId]);
                }
                continue;
            }

            $userAlerts = $alertModel->bdApi_prepareContentForAlerts($userAlerts, $viewingUsers[$userId]);

            bdApi_Template_Simulation_Template::$bdApi_visitor = $viewingUsers[$userId];
            $userAlerts = bdApi_ViewApi_Helper_Alert::getTemplates(bdApi_Template_Simulation_View::create(),
                $userAlerts, $alertModel->bdApi_getAlertHandlers());

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

            $pingDataRef['object_data'] = $alertModel->prepareApiDataForAlert($alertRef);
            if (isset($alertRef['template'])) {
                $pingDataRef['object_data']['notification_html'] = strval($alertRef['template']);
            }
            if (!is_numeric($alertRef['alert_id'])
                && !empty($alertRef['extra']['object_data'])
            ) {
                // fake alert, use the included object_data
                $pingDataRef['object_data'] = array_merge($pingDataRef['object_data'], $alertRef['extra']['object_data']);
            }
        }

        return $pingDataMany;
    }

    protected function _preparePingDataManyPost($pingDataMany)
    {
        // TODO: do anything here?
        return $pingDataMany;
    }

    protected function _preparePingDataManyUser($pingDataMany)
    {
        // TODO: do anything here?
        return $pingDataMany;
    }

    protected function _preparePingData_getViewingUsers($userIds)
    {
        static $allUsers = array();
        $users = array();

        /* @var $userModel XenForo_Model_User */
        $userModel = $this->getModelFromCache('XenForo_Model_User');

        $dbUserIds = array();
        foreach ($userIds as $userId) {
            if ($userId == XenForo_Visitor::getUserId()) {
                $users[$userId] = XenForo_Visitor::getInstance()->toArray();
            } elseif ($userId == 0) {
                $users[$userId] = $userModel->getVisitingGuestUser();
            } elseif (isset($allUsers[$userId])) {
                $users[$userId] = $allUsers[$userId];
            } else {
                $dbUserIds[] = $userId;
            }
        }

        if (!empty($dbUserIds)) {
            $dbUsers = $userModel->getUsersByIds($dbUserIds, array('join' => XenForo_Model_User::FETCH_USER_FULL | XenForo_Model_User::FETCH_USER_PERMISSIONS));

            foreach ($dbUsers as $user) {
                $user = $userModel->prepareUser($user);
                $user['permissions'] = XenForo_Permission::unserializePermissions($user['global_permission_cache']);

                $allUsers[$user['user_id']] = $user;
                $users[$user['user_id']] = $user;
            }
        }

        return $users;
    }

    public function isValidTopic(&$topic, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        list($type, $id) = self::parseTopic($topic);

        if ($type != self::TYPE_CLIENT
            && !bdApi_Option::getSubscription($type)
        ) {
            // subscription for this topic type has been disabled
            return false;
        }

        switch ($type) {
            case self::TYPE_NOTIFICATION:
                if ($id === 'me') {
                    // now supports user_notification_me
                    $id = $viewingUser['user_id'];
                    $topic = self::getTopic($type, $id);
                }

                return (($id > 0) AND ($id == $viewingUser['user_id']));
            case self::TYPE_THREAD_POST:
                /* @var $threadModel XenForo_Model_Thread */
                $threadModel = $this->getModelFromCache('XenForo_Model_Thread');
                $thread = $threadModel->getThreadById($id);

                return $thread['user_id'] == $viewingUser['user_id'];
            case self::TYPE_USER:
                if ($id === 'me') {
                    // now supports user_me
                    $id = $viewingUser['user_id'];
                    $topic = self::getTopic($type, $id);
                }

                return (($id > 0) AND ($id == $viewingUser['user_id']));
            case self::TYPE_CLIENT:
                $session = bdApi_Data_Helper_Core::safeGetSession();

                return $session->getOAuthClientId() !== '';
        }

        return false;
    }

    public function verifyIntentOfSubscriber($callback, $mode, $topic, $leaseSeconds, array $extraParams = array())
    {
        $challenge = md5(XenForo_Application::$time . $callback . $mode . $topic . $leaseSeconds);
        $challenge = md5($challenge . XenForo_Application::getConfig()->get('globalSalt'));

        $client = XenForo_Helper_Http::getClient($callback);

        $requestData = array_merge(array(
            'hub.mode' => $mode,
            'hub.topic' => $topic,
            'hub.lease_seconds' => $leaseSeconds,
            'hub.challenge' => $challenge,
        ), $extraParams);
        $client->setParameterGet($requestData);

        $response = $client->request('GET');
        $body = trim($response->getBody());

        if (XenForo_Application::debugMode()) {
            /* @var $logModel bdApi_Model_Log */
            $logModel = $this->getModelFromCache('bdApi_Model_Log');

            $logModel->logRequest('GET', $callback, $requestData, $response->getStatus(), array('message' => $response->getBody()), array(
                'client_id' => '',
                'user_id' => 0,
                'ip_address' => '127.0.0.1',
            ));
        }

        if ($body !== $challenge) {
            return false;
        }

        if ($response->getStatus() < 200 OR $response->getStatus() > 299) {
            return false;
        }

        return true;
    }

    public function deleteSubscriptionsForTopic($type, $id)
    {
        $topic = sprintf('%s_%s', $type, $id);

        $deleted = $this->_getDb()->delete('xf_bdapi_subscription', array('topic = ?' => $topic));

        return $deleted;
    }

    public function deleteSubscriptions($clientId, $type, $id)
    {
        $topic = sprintf('%s_%s', $type, $id);

        $deleted = $this->_getDb()->delete('xf_bdapi_subscription', array(
            'client_id = ?' => $clientId,
            'topic = ?' => $topic,
        ));

        if ($deleted) {
            $this->updateCallbacksForTopic($topic);
        }

        return $deleted;
    }

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $subscriptions = $this->getSubscriptions($conditions, $fetchOptions);
        $list = array();

        foreach ($subscriptions as $id => $subscription) {
            $list[$id] = $subscription['client_id'];
        }

        return $list;
    }

    public function getSubscriptionById($id, array $fetchOptions = array())
    {
        $subscriptions = $this->getSubscriptions(array('subscription_id' => $id), $fetchOptions);

        return reset($subscriptions);
    }

    public function getSubscriptions(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareSubscriptionConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareSubscriptionOrderOptions($fetchOptions);
        $joinOptions = $this->prepareSubscriptionFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $subscriptions = $this->fetchAllKeyed($this->limitQueryResults("
			SELECT subscription.*
				$joinOptions[selectFields]
			FROM `xf_bdapi_subscription` AS subscription
				$joinOptions[joinTables]
			WHERE $whereConditions
				$orderClause
			", $limitOptions['limit'], $limitOptions['offset']), 'subscription_id');

        return $subscriptions;
    }

    public function countSubscriptions(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareSubscriptionConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareSubscriptionOrderOptions($fetchOptions);
        $joinOptions = $this->prepareSubscriptionFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_bdapi_subscription` AS subscription
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
    }

    public function prepareSubscriptionConditions(array $conditions = array(), array $fetchOptions = array())
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['subscription_id'])) {
            if (is_array($conditions['subscription_id'])) {
                if (!empty($conditions['subscription_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "subscription.subscription_id IN (" . $db->quote($conditions['subscription_id']) . ")";
                }
            } else {
                $sqlConditions[] = "subscription.subscription_id = " . $db->quote($conditions['subscription_id']);
            }
        }

        if (isset($conditions['client_id'])) {
            if (is_array($conditions['client_id'])) {
                if (!empty($conditions['client_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "subscription.client_id IN (" . $db->quote($conditions['client_id']) . ")";
                }
            } else {
                $sqlConditions[] = "subscription.client_id = " . $db->quote($conditions['client_id']);
            }
        }

        if (isset($conditions['topic'])) {
            if (is_array($conditions['topic'])) {
                if (!empty($conditions['topic'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "subscription.topic IN (" . $db->quote($conditions['topic']) . ")";
                }
            } else {
                $sqlConditions[] = "subscription.topic = " . $db->quote($conditions['topic']);
            }
        }

        if (isset($conditions['subscribe_date'])) {
            if (is_array($conditions['subscribe_date'])) {
                if (!empty($conditions['subscribe_date'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "subscription.subscribe_date IN (" . $db->quote($conditions['subscribe_date']) . ")";
                }
            } else {
                $sqlConditions[] = "subscription.subscribe_date = " . $db->quote($conditions['subscribe_date']);
            }
        }

        if (isset($conditions['expire_date'])) {
            if (is_array($conditions['expire_date'])) {
                if (!empty($conditions['expire_date'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "subscription.expire_date IN (" . $db->quote($conditions['expire_date']) . ")";
                }
            } else {
                $sqlConditions[] = "subscription.expire_date = " . $db->quote($conditions['expire_date']);
            }
        }

        if (isset($conditions['expired'])) {
            if ($conditions['expired']) {
                $sqlConditions[] = 'subscription.expire_date > 0';
                $sqlConditions[] = 'subscription.expire_date < ' . XenForo_Application::$time;
            } else {
                $sqlConditions[] = 'subscription.expire_date = 0 OR subscription.expire_date > ' . XenForo_Application::$time;
            }
        }

        if (!empty($conditions['filter'])) {

            if (is_array($conditions['filter'])) {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'][0], $conditions['filter'][1], $db);
            } else {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'], 'lr', $db);
            }

            $sqlConditions[] = sprintf('(subscription.callback LIKE %1$s OR subscription.topic LIKE %1$s)', $filterQuoted);
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareSubscriptionFetchOptions(array $fetchOptions = array())
    {
        $selectFields = '';
        $joinTables = '';

        if (!empty($fetchOptions['join'])) {
            if ($fetchOptions['join'] & self::FETCH_CLIENT) {
                $selectFields .= '
					, client.name AS client_name
					, client.description AS client_description
					, client.redirect_uri AS client_redirect_uri';
                $joinTables .= '
					LEFT JOIN `xf_bdapi_client` AS client
					ON (client.client_id = subscription.client_id)';
            }
        }

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareSubscriptionOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
    {
        $choices = array();

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
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

        return array(
            $type,
            $id
        );
    }

}
