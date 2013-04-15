<?php
class bdApiConsumer_XenForo_ControllerPublic_Account extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Account
{
	public function actionExternal()
	{
		$visitor = XenForo_Visitor::getInstance();

		/* var $userExternalModel XenForo_Model_UserExternal */
		$userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');

		$auth = $this->_getUserModel()->getUserAuthenticationObjectByUserId($visitor['user_id']);
		if (!$auth)
		{
			return $this->responseNoPermission();
		}

		$externalAuths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations($visitor['user_id']);

		if ($this->isConfirmedPost())
		{
			$disassociate = $this->_input->filter(array(
				'provider' => XenForo_Input::STRING,
				'disassociate' => XenForo_Input::STRING,
				'disassociate_confirm' => XenForo_Input::STRING
			));

			$provider = bdApiConsumer_Option::getProviderByCode($disassociate['provider']);
			if (empty($provider))
			{
				return $this->responseNoPermission();
			}

			$externalAuth = false;
			foreach ($externalAuths as $_externalAuth)
			{
				if ($_externalAuth['provider'] == $userExternalModel->bdApiConsumer_getProviderCode($provider))
				{
					$externalAuth = $_externalAuth;
				}
			}
			if (empty($externalAuth))
			{
				return $this->responseNoPermission();
			}

			if ($disassociate['disassociate'] && $disassociate['disassociate_confirm'])
			{
				$userExternalModel->deleteExternalAuthAssociation(
					$externalAuth['provider'],
					$externalAuth['provider_key'],
					$visitor['user_id'],
					$userExternalModel->bdApiConsumer_getUserProfileField()
				);

				if (!$auth->hasPassword())
				{
					$this->getModelFromCache('XenForo_Model_UserConfirmation')->resetPassword($visitor['user_id']);
				}
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('account/external')
			);
		}
		else
		{
			$providers = bdApiConsumer_Option::getProviders();

			$viewParams = array(
				'hasPassword' => $auth->hasPassword(),

				'externalAuths' => $externalAuths,
				'providers' => $providers,
			);

			return $this->_getWrapper(
				'account', 'bdApiConsumer',
				$this->responseView('bdApiConsumer_ViewPublic_Account_External', 'bdapi_consumer_account_external', $viewParams)
			);
		}
	}
}