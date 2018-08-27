<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Util\PageNav;

class Notification extends AbstractController
{
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        $this->assertRegistrationRequired();
    }

    public function actionGetIndex()
    {
        $params = $this
            ->params()
            ->definePageNav();

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');

        $finder = $alertRepo->findAlertsForUser(
            \XF::visitor()->user_id,
            \XF::$time - $this->options()->alertExpiryDays * 86400
        );

        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $notifications = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'notifications' => $notifications,
            'notifications_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'notifications');

        return $this->api($data);
    }
}
