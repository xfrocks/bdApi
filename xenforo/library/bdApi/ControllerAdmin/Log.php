<?php

class bdApi_ControllerAdmin_Log extends XenForo_ControllerAdmin_Abstract
{
    public function actionIndex()
    {
        $logModel = $this->_getLogModel();

        $conditions = array();
        $fetchOptions = array(
            'order' => 'request_date',
            'direction' => 'desc',
        );

        $fetchOptions['page'] = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $fetchOptions['limit'] = 50;

        $filter = $this->_input->filterSingle('_filter', XenForo_Input::ARRAY_SIMPLE);
        if ($filter && isset($filter['value'])) {
            $conditions['filter'] = array(
                $filter['value'],
                empty($filter['prefix']) ? 'lr' : 'r'
            );
            $filterView = true;
        } else {
            $filterView = false;
        }

        $logs = $logModel->getLogs($conditions, $fetchOptions);
        $total = $logModel->countLogs($conditions, $fetchOptions);

        $viewParams = array(
            'logs' => $logs,

            'page' => $fetchOptions['page'],
            'perPage' => $fetchOptions['limit'],
            'total' => $total,

            'filterView' => $filterView,
            'filterMore' => ($filterView AND $total > $fetchOptions['limit'])
        );

        return $this->responseView('bdApi_ViewAdmin_Log_List', 'bdapi_log_list', $viewParams);
    }

    public function actionDetail()
    {
        $logId = $this->_input->filterSingle('log_id', XenForo_Input::UINT);
        $log = $this->_getLogModel()->getLogById($logId);

        if (empty($log)) {
            return $this->responseError(new XenForo_Phrase('bdapi_log_not_found'), 404);
        }

        /* @var $clientModel bdApi_Model_Client */
        $clientModel = $this->getModelFromCache('bdApi_Model_Client');
        /* @var $userModel XenForo_Model_User */
        $userModel = $this->getModelFromCache('XenForo_Model_User');

        $viewParams = array(
            'log' => $log,
            'client' => $clientModel->getClientById($log['client_id']),
            'user' => $userModel->getUserById($log['user_id']),
        );

        return $this->responseView('bdApi_ViewAdmin_Log_Detail', 'bdapi_log_detail', $viewParams);
    }

    /**
     * @return bdApi_Model_Log
     */
    protected function _getLogModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdApi_Model_Log');
    }
}
