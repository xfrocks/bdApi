<?php

class bdApi_ControllerAdmin_Token extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$tokenModel = $this->_getTokenModel();
		$tokens = $tokenModel->getTokens(
				array(
				),
				array(
						'join' => bdApi_Model_Token::FETCH_CLIENT + bdApi_Model_Token::FETCH_USER,
						'order' => 'issue_date',
						'direction' => 'desc',
				)
		);

		$viewParams = array(
				'tokens' => $tokens
		);

		return $this->responseView('bdApi_ViewAdmin_Token_List', 'bdapi_token_list', $viewParams);
	}

	public function actionDelete()
	{
		$id = $this->_input->filterSingle('token_id', XenForo_Input::UINT);
		$token = $this->_getTokenOrError($id);

		if ($this->isConfirmedPost())
		{
			$dw = $this->_getTokenDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('api-tokens')
			);
		}
		else
		{
			$viewParams = array(
					'token' => $token
			);

			return $this->responseView('bdApi_ViewAdmin_Token_Delete', 'bdapi_token_delete', $viewParams);
		}
	}

	public function actionAdd()
	{
		if ($this->isConfirmedPost())
		{
			$clientId = $this->_input->filterSingle('client_id', XenForo_Input::UINT);
			$client = $this->_getClientOrError($clientId);

			$username = $this->_input->filterSingle('username', XenForo_Input::STRING);
			$user = $this->getModelFromCache('XenForo_Model_User')->getUserByName($username);
			if (empty($user))
			{
				return $this->responseError(new XenForo_Phrase('requested_user_not_found'), 404);
			}

			$scopes = $this->_input->filterSingle('scopes', XenForo_Input::ARRAY_SIMPLE);
			$scopes = implode(',', array_map('trim', $scopes));

			$ttl = $this->_input->filterSingle('ttl', XenForo_Input::UINT);

			/* @var $dw bdApi_DataWriter_Token */
			$dw = XenForo_DataWriter::create('bdApi_DataWriter_Token');

			$dw->set('token_text', $this->_getOAuth2Model()->getServer()->genAccessTokenPublic());
			$dw->set('client_id', $client['client_id']);
			$dw->set('expire_date', XenForo_Application::$time + $ttl);
			$dw->set('user_id', $user['user_id']);
			$dw->set('scope', $scopes);
			$dw->save();

			return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					XenForo_Link::buildAdminLink('api-tokens')
			);
		}
		else
		{
			$viewParams = array(
					'clients' => $this->_getClientModel()->getList(),
					'scopes' => $this->_getOAuth2Model()->getSystemSupportedScopes(),
			);

			return $this->responseView('bdApi_ViewAdmin_Token_Add', 'bdapi_token_add', $viewParams);
		}
	}

	protected function _getClientOrError($id, array $fetchOptions = array())
	{
		$info = $this->_getClientModel()->getClientById($id, $fetchOptions);

		if (empty($info))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_client_not_found'), 404));
		}

		return $info;
	}

	protected function _getTokenOrError($id, array $fetchOptions = array())
	{
		$info = $this->_getTokenModel()->getTokenById($id, $fetchOptions);

		if (empty($info))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_token_not_found'), 404));
		}

		return $info;
	}

	/**
	 * @return bdApi_Model_OAuth2
	 */
	protected function _getOAuth2Model()
	{
		return $this->getModelFromCache('bdApi_Model_OAuth2');
	}

	/**
	 * @return bdApi_Model_Client
	 */
	protected function _getClientModel()
	{
		return $this->getModelFromCache('bdApi_Model_Client');
	}

	/**
	 * @return bdApi_Model_Token
	 */
	protected function _getTokenModel()
	{
		return $this->getModelFromCache('bdApi_Model_Token');
	}

	/**
	 * @return bdApi_DataWriter_Token
	 */
	protected function _getTokenDataWriter()
	{
		return XenForo_DataWriter::create('bdApi_DataWriter_Token');
	}
}