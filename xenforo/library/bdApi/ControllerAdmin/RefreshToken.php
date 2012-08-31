<?php

class bdApi_ControllerAdmin_RefreshToken extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$refreshTokenModel = $this->_getRefreshTokenModel();
		$refreshTokens = $refreshTokenModel->getRefreshTokens();
		
		$viewParams = array(
			'refreshTokens' => $refreshTokens
		);
		
		return $this->responseView('bdApi_ViewAdmin_RefreshToken_List', 'bdapi_refresh_token_list', $viewParams);
	}
	
	public function actionAdd()
	{
		$viewParams = array(
			'refreshToken' => array(),
			'allClient' => $this->getModelFromCache('bdApi_Model_Client')->getList(),
		);
		
		return $this->responseView('bdApi_ViewAdmin_RefreshToken_Edit', 'bdapi_refresh_token_edit', $viewParams);
	}
	
	public function actionEdit()
	{
		$id = $this->_input->filterSingle('refresh_token_id', XenForo_Input::UINT);
		$refreshToken = $this->_getRefreshTokenOrError($id);
		
		$viewParams = array(
			'refreshToken' => $refreshToken,
			'allClient' => $this->getModelFromCache('bdApi_Model_Client')->getList(),
		);
		
		return $this->responseView('bdApi_ViewAdmin_RefreshToken_Edit', 'bdapi_refresh_token_edit', $viewParams);
	}
	
	public function actionSave()
	{
		$this->_assertPostOnly();
		
		$id = $this->_input->filterSingle('refresh_token_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array(
			'client_id' => XenForo_Input::UINT,
			'refresh_token_text' => XenForo_Input::STRING,
			'expire_date' => XenForo_Input::UINT,
			'user_id' => XenForo_Input::UINT,
			'scope' => XenForo_Input::STRING
		));
		
		$dw = $this->_getRefreshTokenDataWriter();
		if ($id)
		{
			$dw->setExistingData($id);
		}
		$dw->bulkSet($dwInput);
		
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('api-refresh-tokens')
		);
	}
	
	public function actionDelete()
	{
		$id = $this->_input->filterSingle('refresh_token_id', XenForo_Input::UINT);
		$refreshToken = $this->_getRefreshTokenOrError($id);
		
		if ($this->isConfirmedPost())
		{
			$dw = $this->_getRefreshTokenDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('api-refresh-tokens')
			);
		}
		else
		{
			$viewParams = array(
				'refreshToken' => $refreshToken
			);

			return $this->responseView('bdApi_ViewAdmin_RefreshToken_Delete', 'bdapi_refresh_token_delete', $viewParams);
		}
	}
	
	
	protected function _getRefreshTokenOrError($id, array $fetchOptions = array())
	{
		$info = $this->_getRefreshTokenModel()->getRefreshTokenById($id, $fetchOptions);
		
		if (empty($info))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_refresh_token_not_found'), 404));
		}
		
		return $info;
	}
	
	/**
	 * @return bdApi_Model_RefreshToken
	 */
	protected function _getRefreshTokenModel()
	{
		return $this->getModelFromCache('bdApi_Model_RefreshToken');
	}
	
	/**
	 * @return bdApi_DataWriter_RefreshToken
	 */
	protected function _getRefreshTokenDataWriter()
	{
		return XenForo_DataWriter::create('bdApi_DataWriter_RefreshToken');
	}
}