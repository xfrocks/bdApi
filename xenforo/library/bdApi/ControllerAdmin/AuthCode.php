<?php
/* Start auto-generated lines of code. Change made will be overwriten... */

class bdApi_ControllerAdmin_AuthCode_Generated extends XenForo_ControllerAdmin_Abstract {

	public function actionIndex() {
		$authCodeModel = $this->_getAuthCodeModel();
		$authCodes = $authCodeModel->getAuthCodes();
		
		$viewParams = array(
			'authCodes' => $authCodes
		);
		
		return $this->responseView('bdApi_ViewAdmin_AuthCode_List', 'bdapi_auth_code_list', $viewParams);
	}
	
	public function actionAdd() {
		$viewParams = array(
			'authCode' => array(),
			'allClient' => $this->getModelFromCache('bdApi_Model_Client')->getList(),
		);
		
		return $this->responseView('bdApi_ViewAdmin_AuthCode_Edit', 'bdapi_auth_code_edit', $viewParams);
	}
	
	public function actionEdit() {
		$id = $this->_input->filterSingle('auth_code_id', XenForo_Input::UINT);
		$authCode = $this->_getAuthCodeOrError($id);
		
		$viewParams = array(
			'authCode' => $authCode,
			'allClient' => $this->getModelFromCache('bdApi_Model_Client')->getList(),
		);
		
		return $this->responseView('bdApi_ViewAdmin_AuthCode_Edit', 'bdapi_auth_code_edit', $viewParams);
	}
	
	public function actionSave() {
		$this->_assertPostOnly();
		
		$id = $this->_input->filterSingle('auth_code_id', XenForo_Input::UINT);

		$dwInput = $this->_input->filter(array('client_id' => 'uint', 'auth_code_text' => 'string', 'redirect_uri' => 'string', 'expire_date' => 'uint', 'user_id' => 'uint', 'scope' => 'string'));
		
		$dw = $this->_getAuthCodeDataWriter();
		if ($id) {
			$dw->setExistingData($id);
		}
		$dw->bulkSet($dwInput);
		

		
		$this->_prepareDwBeforeSaving($dw);
		
		$dw->save();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('api-auth-codes')
		);
	}
	
	public function actionDelete() {
		$id = $this->_input->filterSingle('auth_code_id', XenForo_Input::UINT);
		$authCode = $this->_getAuthCodeOrError($id);
		
		if ($this->isConfirmedPost()) {
			$dw = $this->_getAuthCodeDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('api-auth-codes')
			);
		} else {
			$viewParams = array(
				'authCode' => $authCode
			);

			return $this->responseView('bdApi_ViewAdmin_AuthCode_Delete', 'bdapi_auth_code_delete', $viewParams);
		}
	}
	
	
	protected function _getAuthCodeOrError($id, array $fetchOptions = array()) {
		$info = $this->_getAuthCodeModel()->getAuthCodeById($id, $fetchOptions);
		
		if (empty($info)) {
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_auth_code_not_found'), 404));
		}
		
		return $info;
	}
	
	/**
	 * @return bdApi_Model_AuthCode
	 */
	protected function _getAuthCodeModel() {
		return $this->getModelFromCache('bdApi_Model_AuthCode');
	}
	
	/**
	 * @return bdApi_DataWriter_AuthCode
	 */
	protected function _getAuthCodeDataWriter() {
		return XenForo_DataWriter::create('bdApi_DataWriter_AuthCode');
	}
	
	protected function _prepareDwBeforeSaving(bdApi_DataWriter_AuthCode $dw) {
		// this method should be overriden if datawriter requires special treatments
	}
}

/* End auto-generated lines of code. Feel free to make changes below */
class bdApi_ControllerAdmin_AuthCode extends bdApi_ControllerAdmin_AuthCode_Generated {
	// customized actions and whatelse should go here
}