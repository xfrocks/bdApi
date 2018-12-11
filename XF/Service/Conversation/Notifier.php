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

        $message = $message ?: $this->conversation->FirstMessage;
        $sender = $sender ?: $message->User;

        foreach ($notifyUsers as $user) {
            if ($sender->user_id == $user->user_id) {
                continue;
            }

            /** @var Subscription $subscriptionRepo */
            $subscriptionRepo = $this->repository('Xfrocks\Api:Subscription');
            $subscriptionRepo->pingConversationMessage(
                $actionType,
                $message,
                $user,
                $sender
            );
        }

        return $sent;
    }
}
