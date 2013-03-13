<?php

class bdApi_ControllerAdmin_AuthCode extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$authCodeModel = $this->_getAuthCodeModel();
		$authCodes = $authCodeModel->getAuthCodes(
			array(
			),
			array(
				'join' => bdApi_Model_AuthCode::FETCH_CLIENT + bdApi_Model_AuthCode::FETCH_USER,
				'order' => 'issue_date',
				'direction' => 'desc',
			)
		);
		
		$viewParams = array(
			'authCodes' => $authCodes
		);
		
		return $this->responseView('bdApi_ViewAdmin_AuthCode_List', 'bdapi_auth_code_list', $viewParams);
	}
	
	public function actionDelete()
	{
		$id = $this->_input->filterSingle('auth_code_id', XenForo_Input::UINT);
		$authCode = $this->_getAuthCodeOrError($id);
		
		if ($this->isConfirmedPost())
		{
			$dw = $this->_getAuthCodeDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('api-auth-codes')
			);
		}
		else
		{
			$viewParams = array(
				'authCode' => $authCode
			);

			return $this->responseView('bdApi_ViewAdmin_AuthCode_Delete', 'bdapi_auth_code_delete', $viewParams);
		}
	}
	
	
	protected function _getAuthCodeOrError($id, array $fetchOptions = array())
	{
		$info = $this->_getAuthCodeModel()->getAuthCodeById($id, $fetchOptions);
		
		if (empty($info))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_auth_code_not_found'), 404));
		}
		
		return $info;
	}
	
	/**
	 * @return bdApi_Model_AuthCode
	 */
	protected function _getAuthCodeModel()
	{
		return $this->getModelFromCache('bdApi_Model_AuthCode');
	}
	
	/**
	 * @return bdApi_DataWriter_AuthCode
	 */
	protected function _getAuthCodeDataWriter()
	{
		return XenForo_DataWriter::create('bdApi_DataWriter_AuthCode');
	}
}