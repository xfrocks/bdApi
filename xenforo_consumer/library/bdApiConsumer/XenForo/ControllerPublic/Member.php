<?php

class bdApiConsumer_XenForo_ControllerPublic_Member extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Member
{
	public function actionMember()
	{
		$response = parent::actionMember();

		if (bdApiConsumer_Option::get('takeOver', 'profile'))
		{
			if ($response instanceof XenForo_ControllerResponse_View AND !empty($response->params['user']))
			{
				$userExternalModel = $this->getModelFromCache('XenForo_Model_UserExternal');
				$auths = $userExternalModel->bdApiConsumer_getExternalAuthAssociations($response->params['user']['user_id']);

				foreach ($auths as $auth)
				{
					if (!empty($auth['extra_data']['links']['permalink']))
					{
						return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $auth['extra_data']['links']['permalink']);
					}
				}
			}
		}

		return $response;
	}

}
