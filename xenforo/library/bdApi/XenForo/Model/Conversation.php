<?php

class bdApi_XenForo_Model_Conversation extends XFCP_bdApi_XenForo_Model_Conversation
{
    public function insertConversationAlert(
        array $conversation,
        array $alertUser,
        $action,
        array $triggerUser = null,
        array $extraData = null,
        array &$messageInfo = null
    ) {
        parent::insertConversationAlert(
            $conversation,
            $alertUser,
            $action,
            $triggerUser,
            $extraData,
            $messageInfo
        );

        if (!bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_NOTIFICATION)
            || !bdApi_Option::get('userNotificationConversation')
        ) {
            return;
        }

        if (!$triggerUser) {
            $triggerUser = array(
                'user_id' => $conversation['last_message_user_id'],
                'username' => $conversation['last_message_username']
            );
        }

        if ($triggerUser['user_id'] == $alertUser['user_id']) {
            return;
        }

        if (empty($extraData)) {
            $extraData = array();
        }
        $extraData['object_data'] = array(
            'notification_id' => 0,
            'notification_html' => '',
        );
        $extraData['object_data']['message'] = array(
            'conversation_id' => $conversation['conversation_id'],
            'title' => $conversation['title'],
            'message' => XenForo_Template_Helper_Core::callHelper('snippet', array(
                $messageInfo['message'],
                140,
                array(
                    'stripQuote' => true,
                )
            )),
        );
        if (isset($extraData['message_id'])) {
            $extraData['object_data']['message']['message_id'] = $extraData['message_id'];
        } else {
            $extraData['object_data']['message']['message_id'] = $conversation['first_message_id'];
        }

        $fakeAlert = array(
            'alert_id' => 0,
            'alerted_user_id' => $alertUser['user_id'],
            'user_id' => $triggerUser['user_id'],
            'username' => $triggerUser['username'],
            'content_type' => 'conversation',
            'content_id' => $conversation['conversation_id'],
            'action' => $action,
            'event_date' => XenForo_Application::$time,
            'view_date' => 0,
            'extra_data' => serialize($extraData),
        );

        if ($fakeAlert['alerted_user_id'] > 0) {
            /* @var $alertModel bdApi_XenForo_Model_Alert */
            $alertModel = $this->getModelFromCache('XenForo_Model_Alert');
            $option = $alertModel->bdApi_getUserNotificationOption($fakeAlert['alerted_user_id']);
        }

        if (!empty($option)) {
            if ($fakeAlert['user_id'] == XenForo_Visitor::getUserId()) {
                $fakeAlert = array_merge($fakeAlert, XenForo_Visitor::getInstance()->toArray());
            } else {
                /** @var XenForo_Model_User $userModel */
                $userModel = $this->getModelFromCache('XenForo_Model_User');
                $user = $userModel->getUserById($fakeAlert['user_id']);
                $fakeAlert = array_merge($fakeAlert, $user);
            }

            /* @var $subscriptionModel bdApi_Model_Subscription */
            $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
            $subscriptionModel->ping(
                $option,
                'insert',
                bdApi_Model_Subscription::TYPE_NOTIFICATION,
                $fakeAlert,
                bdApi_Option::get('pingNotificationsTTL')
            );
        }

        return;
    }

    public function markConversationAsRead(
        $conversationId,
        $userId,
        $newReadDate,
        $lastMessageDate = 0,
        $updateVisitor = true
    ) {
        if (bdApi_Option::getSubscription(bdApi_Model_Subscription::TYPE_NOTIFICATION)) {
            /** @var bdApi_XenForo_Model_Alert $alertModel */
            $alertModel = $this->getModelFromCache('XenForo_Model_Alert');
            $userOption = $alertModel->bdApi_getUserNotificationOption($userId);
            if (bdApi_Option::get('markReadAsPing') && !empty($userOption)) {
                /* @var $subscriptionModel bdApi_Model_Subscription */
                $subscriptionModel = $this->getModelFromCache('bdApi_Model_Subscription');
                $subscriptionModel->ping(
                    $userOption,
                    'read',
                    bdApi_Model_Subscription::TYPE_NOTIFICATION,
                    bdApi_AlertHandler_Ping::fakeAlert(
                        $userId,
                        array('read_conversation_id' => $conversationId)
                    ),
                    bdApi_Option::get('pingNotificationsTTL')
                );
            }
        }

        parent::markConversationAsRead($conversationId, $userId, $newReadDate, $lastMessageDate, $updateVisitor);
    }
}
