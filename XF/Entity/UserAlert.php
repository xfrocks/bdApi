<?php

namespace Xfrocks\Api\XF\Entity;

use XF\Util\Php;
use Xfrocks\Api\Repository\Subscription;

class UserAlert extends XFCP_UserAlert
{
    /**
     * @return void
     */
    protected function _postSave()
    {
        parent::_postSave();

        static $userOptions = [];

        /** @var Subscription $subRepo */
        $subRepo = $this->repository('Xfrocks\Api:Subscription');

        if (Subscription::getSubOption(Subscription::TYPE_NOTIFICATION)) {
            if ($this->alerted_user_id > 0) {
                /** @var string $subColumn */
                $subColumn = \XF::options()->bdApi_subscriptionColumnUserNotification;
                if (!isset($userOptions[$this->alerted_user_id])) {
                    $userOptions[$this->alerted_user_id] = $this->db()->fetchOne('
                        SELECT `' . $subColumn . '`
                        FROM `xf_user_option`
                        WHERE user_id = ?
                    ', $this->alerted_user_id);
                }

                $option = $userOptions[$this->alerted_user_id];
                if (is_string($option) && strlen($option) > 0) {
                    $option = Php::safeUnserialize($option);
                }

                if (!is_array($option)) {
                    $option = [];
                }
            } else {
                $option = $subRepo->getClientSubscriptionsData();
            }

            if (count($option) > 0) {
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
