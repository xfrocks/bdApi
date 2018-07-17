<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\ConversationMaster;
use XF\Mvc\ParameterBag;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class ConversationMessage extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->message_id) {
            return $this->actionSingle($params->message_id);
        }

        $orderChoices = [
            'natural' => ['message_date', 'ASC'],
            'natural_reverse' => ['message_date', 'DESC']
        ];

        $params = $this
            ->params()
            ->define('conversation_id', 'uint', 'conversation id to filter')
            ->define('before', 'uint', 'date to get older messages')
            ->define('after', 'uint', 'date to get newer messages')
            ->definePageNav()
            ->defineOrder($orderChoices);

        $this->assertRegistrationRequired();

        /** @var ConversationMaster $conversation */
        $conversation = $this->assertRecordExists('XF:ConversationMaster', $params['conversation_id']);
        if (!$conversation->canView($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Repository\ConversationMessage $convoMessageRepo */
        $convoMessageRepo = $this->repository('XF:ConversationMessage');

        $finder = $convoMessageRepo->findMessagesForConversationView($conversation);
        $this->applyMessagesFilters($finder, $params);

        $total = $finder->total();
        $messages = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'messages' => $messages,
            'messages_total' => $total
        ];

        $this->transformEntityIfNeeded($data, 'conversation', $conversation);
        PageNav::addLinksToData($data, $params, $total, 'conversation-messages');

        return $this->api($data);
    }

    public function actionSingle($messageId)
    {
        $message = $this->assertViewableMessage($messageId);

        $data = [
            'message' => $this->transformEntityLazily($message)
        ];

        return $this->api($data);
    }

    protected function assertViewableMessage($messageId, array $extraWith = [])
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $this->assertRecordExists('XF:ConversationMessage', $messageId, $extraWith);
        if (!$message->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $message;
    }

    protected function applyMessagesFilters(\XF\Finder\ConversationMessage $finder, Params $params)
    {
        if ($params['order'] === 'natural_reverse') {
            $finder->resetOrder()
                   ->order('message_date', 'DESC');
        }

        if ($params['before'] > 0) {
            $finder->where('message_date', '<', $params['before']);
        }

        if ($params['after'] > 0) {
            $finder->where('message_date', '>', $params['after']);
        }

        $params->limitFinderByPage($finder);
    }
}
