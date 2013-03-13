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
	
	public function actionDelete() {
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