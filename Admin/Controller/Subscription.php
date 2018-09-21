<?php

namespace Xfrocks\Api\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Subscription extends AbstractController
{
    public function actionIndex()
    {
        $finder = $this->finder('Xfrocks\Api:Subscription');
        $finder->order('subscribe_date', 'DESC');

        $page = $this->filterPage();
        $perPage = 20;

        $finder->limitByPage($page, $perPage);

        $total = $finder->total();

        $this->assertValidPage($page, $perPage, $total, 'api-subscriptions');

        return $this->view('Xfrocks\Api:Subscription\Index', 'bdapi_subscription_list', [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'subscriptions' => $finder->fetch()
        ]);
    }
}
