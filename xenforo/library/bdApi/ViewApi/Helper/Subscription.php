<?php

class bdApi_ViewApi_Helper_Subscription
{
    public static function prepareDiscoveryParams(array &$params, Zend_Controller_Response_Http $response, $topicType, $topicId, $selfLink, $subscriptionOption)
    {
        if (!bdApi_Option::getSubscription($topicType)) {
            // subscription for this topic type has been disabled
            return false;
        }

        // subscription discovery
        $hubLink = bdApi_Data_Helper_Core::safeBuildApiLink('subscriptions', null, array(
            'hub.topic' => bdApi_Model_Subscription::getTopic($topicType, $topicId),
            'oauth_token' => '',
        ));
        $response->setHeader('Link', sprintf('<%s>; rel=hub', $hubLink));
        $response->setHeader('Link', sprintf('<%s>; rel=self', $selfLink));

        // subscription info
        if (!empty($subscriptionOption)) {
            $subscriptionOption = @unserialize($subscriptionOption);
            if (!empty($subscriptionOption['subscriptions'])) {
                /* @var $session bdApi_Session */
                $session = XenForo_Application::getSession();
                $clientId = $session->getOAuthClientId();

                foreach ($subscriptionOption['subscriptions'] as $subscription) {
                    if ($subscription['client_id'] == $clientId) {
                        $params['subscription_callback'] = $subscription['callback'];
                    }
                }
            }
        }

        return true;
    }

}
