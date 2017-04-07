<?php

class bdApi_ControllerApi_Notification extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $this->_assertRegistrationRequired();

        $alertModel = $this->_getAlertModel();
        $visitor = XenForo_Visitor::getInstance();

        $pageNavParams = array();
        list($limit, $page) = $this->filterLimitAndPage($pageNavParams);

        $alertResults = $alertModel->getAlertsForUser(
            $visitor['user_id'],
            XenForo_Model_Alert::FETCH_MODE_RECENT,
            array(
                'page' => $page,
                'limit' => $limit
            )
        );
        $alerts =& $alertResults['alerts'];

        $total = $alertModel->countAlertsForUser($visitor['user_id']);

        $data = array(
            'notifications' => $this->_filterDataMany($this->_getAlertModel()->prepareApiDataForAlerts($alerts)),
            'notifications_total' => $total,
            'links' => array(
                'read' => bdApi_Data_Helper_Core::safeBuildApiLink('notifications/read'),
            ),

            '_alerts' => $alerts,
            '_alertHandlers' => $alertResults['alertHandlers'],
        );

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'notifications', array(), $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Notification_List', $data);
    }

    public function actionPostRead()
    {
        $this->_assertRegistrationRequired();
        $visitor = XenForo_Visitor::getInstance();

        if ($visitor['alerts_unread'] > 0) {
            $this->_getAlertModel()->markAllAlertsReadForUser($visitor['user_id']);
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }


    public function actionGetContent()
    {
        $id = $this->_input->filterSingle('notification_id', XenForo_Input::UINT);
        $alert = $this->_getAlertModel()->getAlertById($id);
        if (empty($alert)) {
            return $this->responseNoPermission();
        }

        $visitor = XenForo_Visitor::getInstance();
        if ($visitor['user_id'] != $alert['alerted_user_id']) {
            return $this->responseNoPermission();
        }

        $controllerResponse = $this->_actionGetContent_getControllerResponse($alert);
        if (!empty($controllerResponse)) {
            return $controllerResponse;
        }

        // alert content type not recognized...
        return $this->_actionGetContent_getControllerResponseNop($alert);
    }


    protected function _actionGetContent_getControllerResponseNop(array $alert = null)
    {
        return $this->responseData('bdApi_ViewApi_Notification_Content', array(
            'notification_id' => !empty($alert['alert_id']) ? $alert['alert_id'] : null,
        ));
    }

    protected function _actionGetContent_getControllerResponse(array $alert)
    {
        switch ($alert['content_type']) {
            case 'conversation':
                // NOTE: currently XenForo does not send this type of alert
                switch ($alert['action']) {
                    case 'insert':
                    case 'join':
                    case 'reply':
                        $this->_request->setParam('conversation_id', $alert['content_id']);
                        return $this->responseReroute('bdApi_ControllerApi_ConversationMessage', 'get-index');
                }
                $this->_request->setParam('conversation_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_Conversation', 'get-index');

            case 'thread':
                $this->_request->setParam('thread_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_Post', 'get-index');
            case 'post':
                $this->_request->setParam('page_of_post_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_Post', 'get-index');

            case 'user':
                switch ($alert['action']) {
                    case 'following':
                        $this->_request->setParam('user_id', $alert['content_id']);
                        return $this->responseReroute('bdApi_ControllerApi_User', 'get-followers');
                    case 'post_copy':
                    case 'post_move':
                    case 'thread_merge':
                        $extra = @unserialize($alert['extra_data']);
                        if (!empty($extra['targetLink'])) {
                            $this->_request->setParam('link', $extra['targetLink']);
                            return $this->responseReroute('bdApi_ControllerApi_Tool', 'get-parse-link');
                        }
                        break;
                    case 'thread_move':
                        $extra = @unserialize($alert['extra_data']);
                        if (!empty($extra['link'])) {
                            $this->_request->setParam('link', $extra['link']);
                            return $this->responseReroute('bdApi_ControllerApi_Tool', 'get-parse-link');
                        }
                        break;
                }
                $this->_request->setParam('user_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_User', 'get-index');

            case 'profile_post':
                $this->_request->setParam('profile_post_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_ProfilePost', 'get-comments');
            case 'profile_post_comment':
                // NOTE: currently XenForo does not send this type of alert
                $this->_request->setParam('page_of_comment_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_ProfilePost', 'get-comments');
        }

        return null;
    }

    /**
     *
     * @return bdApi_XenForo_Model_Alert
     */
    protected function _getAlertModel()
    {
        return $this->getModelFromCache('XenForo_Model_Alert');
    }

}
