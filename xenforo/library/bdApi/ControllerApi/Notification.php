<?php

class bdApi_ControllerApi_Notification extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $this->_assertRegistrationRequired();

        $alertModel = $this->_getAlertModel();
        $visitor = XenForo_Visitor::getInstance();

        $pageNavParams = array();
        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $limit = XenForo_Application::get('options')->alertsPerPage;

        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

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
            case 'thread':
                $this->_request->setParam('thread_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_Post', 'get-index');
            case 'post':
                $this->_request->setParam('page_of_post_id', $alert['content_id']);
                return $this->responseReroute('bdApi_ControllerApi_Post', 'get-index');
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
