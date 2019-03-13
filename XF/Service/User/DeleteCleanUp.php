<?php

namespace Xfrocks\Api\XF\Service\User;

use Xfrocks\Api\Repository\Subscription;

class DeleteCleanUp extends XFCP_DeleteCleanUp
{
    /**
     * @param \XF\App $app
     * @param int $userId
     * @param string $userName
     */
    public function __construct(\XF\App $app, $userId, $userName)
    {
        parent::__construct($app, $userId, $userName);

        $this->deletes['xf_bdapi_auth_code'] = 'user_id = ?';
        $this->deletes['xf_bdapi_refresh_token'] = 'user_id = ?';
        $this->deletes['xf_bdapi_token'] = 'user_id = ?';
        $this->deletes['xf_bdapi_user_scope'] = 'user_id = ?';

        $this->steps[] = 'stepDeleteApiSubscriptions';
    }

    /**
     * @return void
     */
    public function stepDeleteApiSubscriptions()
    {
        $app = $this->app;
        $options = $app->options();

        if ($options->bdApi_subscriptionUser
            || $options->bdApi_subscriptionUserNotification
        ) {
            /** @var Subscription $subRepo */
            $subRepo = $this->repository('Xfrocks\Api:Subscription');
            $subRepo->deleteSubscriptionsForTopic(Subscription::TYPE_USER, $this->userId);
        }
    }
}
