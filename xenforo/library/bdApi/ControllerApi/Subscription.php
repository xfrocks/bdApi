<?php

class bdApi_ControllerApi_Subscription extends bdApi_ControllerApi_Abstract
{
    public function actionPostIndex()
    {
        $input = $this->_input->filter(array(
            'hub_callback' => XenForo_Input::STRING,
            'hub_mode' => XenForo_Input::STRING,
            'hub_topic' => XenForo_Input::STRING,
            'hub_lease_seconds' => XenForo_Input::STRING,
        ));

        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();
        $clientId = $session->getOAuthClientId();
        $isSessionClientId = true;
        if (empty($clientId)) {
            $clientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
            $isSessionClientId = false;
        }
        if (empty($clientId)) {
            return $this->responseNoPermission();
        }

        if (!Zend_Uri::check($input['hub_callback'])) {
            return $this->_responseError(new XenForo_Phrase('bdapi_subscription_callback_is_required'));
        }

        if (!in_array($input['hub_mode'], array(
            'subscribe',
            'unsubscribe'
        ), true)
        ) {
            return $this->_responseError(new XenForo_Phrase('bdapi_subscription_mode_must_valid'));
        }

        $isSubscribe = $input['hub_mode'] === 'subscribe';
        $existingSubscriptions = $this->_getSubscriptionModel()->getSubscriptions(array(
            'client_id' => $clientId,
            'topic' => $input['hub_topic'],
        ));

        if ($isSubscribe) {
            foreach ($existingSubscriptions as $existingSubscription) {
                if ($existingSubscription['callback'] === $input['hub_callback']) {
                    // found existing subscription with the same callback, return asap
                    return $this->_responseSuccess();
                }
            }

            if (!$isSessionClientId) {
                // subscribe requires authenticated session
                return $this->responseNoPermission();
            }

            if (!$this->_getSubscriptionModel()->isValidTopic($input['hub_topic'])) {
                return $this->_responseError(new XenForo_Phrase('bdapi_subscription_topic_not_recognized'));
            }
        }

        if (!$this->_getSubscriptionModel()->verifyIntentOfSubscriber(
            $input['hub_callback'],
            $input['hub_mode'],
            $input['hub_topic'],
            $input['hub_lease_seconds'],
            array('client_id' => $clientId)
        )) {
            return $this->_responseError(new XenForo_Phrase('bdapi_subscription_cannot_verify_intent_of_subscriber'));
        }

        foreach ($existingSubscriptions as $subscription) {
            $dw = XenForo_DataWriter::create('bdApi_DataWriter_Subscription', XenForo_DataWriter::ERROR_SILENT);
            $dw->setOption(bdApi_DataWriter_Subscription::OPTION_UPDATE_CALLBACKS, false);
            $dw->setExistingData($subscription, true);
            $dw->delete();
        }

        if ($isSubscribe) {
            $dw = XenForo_DataWriter::create('bdApi_DataWriter_Subscription');
            $dw->set('client_id', $clientId);
            $dw->set('callback', $input['hub_callback']);
            $dw->set('topic', $input['hub_topic']);
            $dw->set('subscribe_date', XenForo_Application::$time);

            if ($input['hub_lease_seconds'] > 0) {
                $dw->set('expire_date', XenForo_Application::$time + $input['hub_lease_seconds']);
            }

            $dw->save();
        } elseif (count($existingSubscriptions) > 0) {
            $this->_getSubscriptionModel()->updateCallbacksForTopic($input['hub_topic']);
        }

        return $this->_responseSuccess();
    }

    /**
     * @return bdApi_Model_Subscription
     */
    protected function _getSubscriptionModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdApi_Model_Subscription');
    }

    protected function _getScopeForAction($action)
    {
        return false;
    }

    protected function _responseError($error)
    {
        $this->_routeMatch->setResponseType('raw');

        return $this->responseData('bdApi_ViewApi_Subscription_Post', array(
            'httpResponseCode' => 400,
            'message' => $error,
        ));
    }

    protected function _responseSuccess()
    {
        $this->_routeMatch->setResponseType('raw');

        return $this->responseData('bdApi_ViewApi_Subscription_Post', array('httpResponseCode' => 202));
    }
}
