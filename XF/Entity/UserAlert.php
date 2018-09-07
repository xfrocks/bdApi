<?php

namespace Xfrocks\Api\XF\Entity;

use XF\Util\Php;
use Xfrocks\Api\Repository\Subscription;

/**
 * Class UserAlert
 * @package Xfrocks\Api\XF\Entity
 * @inheritdoc
 */
class UserAlert extends XFCP_UserAlert
{
    protected function _postSave()
    {
        parent::_postSave();

        static $userOptions = [];

        /** @var Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');

        if (Subscription::getSubscription(Subscription::TYPE_NOTIFICATION)) {
            if ($this->alerted_user_id > 0) {
                $subColumn = \XF::options()->bdApi_subscriptionColumnUserNotification;
                if (!isset($userOptions[$this->alerted_user_id])) {
                    $userOptions[$this->alerted_user_id] = $this->db()->fetchOne('
                        SELECT `' . $subColumn . '`
                        FROM `xf_user_option`
                        WHERE user_id = ?
                    ', $this->alerted_user_id);
                }

                $option = $userOptions[$this->alerted_user_id];
                if (!empty($option)) {
                    $option = Php::safeUnserialize($option);
                }

                if (empty($option)) {
                    $option = [];
                }
            } else {
                $option = $subRepo->getClientSubscriptionsData();
            }

            if (!empty($option)) {
                $subRepo->ping(
                    $option,
                    'insert',
                    Subscription::TYPE_NOTIFICATION,
                    $this->alert_id
                );
            }
        }
    }
}
