<?php

class bdApi_ControllerApi_Notification extends bdApi_ControllerApi_Abstract
{

    protected function _preDispatch($action)
    {
        $this->_assertRegistrationRequired();

        parent::_preDispatch($action);
    }

    public function actionGetIndex()
    {
        $alertModel = $this->_getAlertModel();
        $visitor = XenForo_Visitor::getInstance();

        $alertResults = $alertModel->getAlertsForUser($visitor['user_id'], XenForo_Model_Alert::FETCH_MODE_POPUP);

        $alerts = array();
        foreach ($alertResults['alerts'] AS $alertId => $alert) {
            if ($alert['unviewed']) {
                $alerts[$alertId] = $alert;
            }
        }

        $data = array(
            'notifications' => $this->_filterDataMany($this->_getAlertModel()->prepareApiDataForAlerts($alerts)),

            '_alerts' => $alerts,
            '_alertHandlers' => $alertResults['alertHandlers'],
        );

        return $this->responseData('bdApi_ViewApi_Notification_List', $data);
    }

    public function actionPostRead()
    {
        $visitor = XenForo_Visitor::getInstance();

        if ($visitor['alerts_unread'] > 0) {
            $this->_getAlertModel()->markAllAlertsReadForUser($visitor['user_id']);
        }

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
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
