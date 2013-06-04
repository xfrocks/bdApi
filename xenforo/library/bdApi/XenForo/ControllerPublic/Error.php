<?php
class bdApi_XenForo_ControllerPublic_Error extends XFCP_bdApi_XenForo_ControllerPublic_Error
{
	public function actionAuthorizeGuest()
	{
		/* @var $oauth2Model bdApi_Model_OAuth2 */
		$oauth2Model = $this->getModelFromCache('bdApi_Model_OAuth2');

		/* @var $clientModel bdApi_Model_Client */
		$clientModel = $oauth2Model->getClientModel();

		$authorizeParams = $oauth2Model->getServer()->getAuthorizeParams();

		$client = $clientModel->getClientById($authorizeParams['client_id']);
		if (empty($client))
		{
			throw new XenForo_Exception(new XenForo_Phrase('bdapi_authorize_error_client_x_not_found', array('client' => $authorizeParams['client_id'])));
		}

		$viewParams = array(
				'client' => $client,
		);

		$view = $this->responseView('bdApi_ViewPublic_Error_AuthorizeGuest', 'bdapi_error_authorize_guest', $viewParams);
		$view->responseCode = 403;

		return $view;
	}
}