<?php

namespace Xfrocks\Api\XF\Service\Conversation;

use Xfrocks\Api\Repository\Subscription;

class Notifier extends XFCP_Notifier
{
    protected function _sendNotifications(
        $actionType,
        array $notifyUsers,
        \XF\Entity\ConversationMessage $message = null,
        \XF\Entity\User $sender = null
    ) {
        $sent = parent::_sendNotifications($actionType, $notifyUsers, $message, $sender);

        foreach ($sent as $user) {
            /** @var Subscription $subscriptionRepo */
            $subscriptionRepo = $this->repository('Xfrocks\Api:Subscription');
            $subscriptionRepo->pingConversationMessage(
                $actionType,
                $message ?: $this->conversation->FirstMessage,
                $user,
                $sender
            );
        }

        return $sent;
    }
}
