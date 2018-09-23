<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Log extends AbstractController
{
    public function actionIndex(ParameterBag $paramBag)
    {
        if ($paramBag->log_id) {
            return $this->rerouteController(__CLASS__, 'view', $paramBag);
        }

        $finder = $this->finder('Xfrocks\Api:Log');
        $finder->with('User');
        $finder->order('request_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 20;

        $total = $finder->total();

        $this->assertValidPage($page, $perPage, $total, 'api-logs');

        return $this->view('Xfrocks\Api:Logs\Index', 'bdapi_log_list', [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'logs' => $finder->fetch()
        ]);
    }

    public function actionView(ParameterBag $paramBag)
    {
        $log = $this->assertRecordExists('Xfrocks\Api:Log', $paramBag->log_id);

        return $this->view('Xfrocks\Api:Logs\View', 'bdapi_log_view', [
            'log' => $log
        ]);
    }
}
