<?php

class bdApi_ControllerAdmin_Subscription extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$subscriptionModel = $this->_getSubscriptionModel();

		$conditions = array();
		$fetchOptions = array('join' => bdApi_Model_Subscription::FETCH_CLIENT);

		$viewParams = $this->getHelper('bdApi_ControllerHelper_Admin')->prepareConditionsAndFetchOptions($conditions, $fetchOptions);

		$subscriptions = $subscriptionModel->getSubscriptions($conditions, $fetchOptions);
		$total = $subscriptionModel->countSubscriptions($conditions, $fetchOptions);

		$viewParams = array_merge($viewParams, array(
			'subscriptions' => $subscriptions,
			'total' => $total,
		));

		return $this->responseView('bdApi_ViewAdmin_Subscription_List', 'bdapi_subscription_list', $viewParams);
	}

	public function actionDetails()
	{
		$id = $this->_input->filterSingle('subscription_id', XenForo_Input::UINT);
		$subscription = $this->_getSubscriptionOrError($id);

		$viewParams = array('subscription' => $subscription);

		return $this->responseView('bdApi_ViewAdmin_Subscription_Details', 'bdapi_subscription_details', $viewParams);
	}

	public function actionDelete()
	{
		$id = $this->_input->filterSingle('subscription_id', XenForo_Input::UINT);
		$subscription = $this->_getSubscriptionOrError($id);

		if ($this->isConfirmedPost())
		{
			$dw = $this->_getSubscriptionDataWriter();
			$dw->setExistingData($id);
			$dw->delete();

			return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, XenForo_Link::buildAdminLink('api-subscriptions'));
		}
		else
		{
			$viewParams = array('subscription' => $subscription);

			return $this->responseView('bdApi_ViewAdmin_Subscription_Delete', 'bdapi_subscription_delete', $viewParams);
		}
	}

	protected function _getSubscriptionOrError($id, array $fetchOptions = array())
	{
		$subscription = $this->_getSubscriptionModel()->getSubscriptionById($id, $fetchOptions);

		if (empty($subscription))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_subscription_not_found'), 404));
		}

		return $subscription;
	}

	protected function _getSubscriptionModel()
	{
		return $this->getModelFromCache('bdApi_Model_Subscription');
	}

	protected function _getSubscriptionDataWriter()
	{
		return XenForo_DataWriter::create('bdApi_DataWriter_Subscription');
	}

}
