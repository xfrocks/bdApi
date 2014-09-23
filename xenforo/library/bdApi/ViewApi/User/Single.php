<?php

class bdApi_ViewApi_User_Single extends bdApi_ViewApi_Base
{
	public function prepareParams()
	{
		if (!empty($this->_params['_user']['user_id']))
		{
			// subscription discovery
			$hubLink = XenForo_Link::buildApiLink('subscriptions', null, array(
				'hub.topic' => bdApi_Model_Subscription::getTopic(bdApi_Model_Subscription::TYPE_USER, $this->_params['_user']['user_id']),
				OAUTH2_TOKEN_PARAM_NAME => '',
			));
			$this->_response->setHeader('Link', sprintf('<%s>; rel=hub', $hubLink));
			$selfLink = XenForo_Link::buildApiLink('users', $this->_params['_user'], array(OAUTH2_TOKEN_PARAM_NAME => ''));
			$this->_response->setHeader('Link', sprintf('<%s>; rel=self', $selfLink));

			// subscription info
			if (!empty($this->_params['_user']['bdapi_user']))
			{
				$userOption = @unserialize($this->_params['_user']['bdapi_user']);
				if (!empty($userOption['subscriptions']))
				{
					$clientId = XenForo_Application::getSession()->getOAuthClientId();
					foreach ($userOption['subscriptions'] as $subscription)
					{
						if ($subscription['client_id'] == $clientId)
						{
							$this->_params['subscription_callback'] = $subscription['callback'];
						}
					}
				}
			}
		}

		return parent::prepareParams();
	}

}
