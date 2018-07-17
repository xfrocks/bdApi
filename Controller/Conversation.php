<?php

namespace Xfrocks\Api\Controller;

use XF\Mvc\ParameterBag;
use Xfrocks\Api\Util\PageNav;

class Conversation extends AbstractController
{
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->conversation_id) {
            return $this->actionSingle($params->conversation_id);
        }

        $this->assertRegistrationRequired();

        $params = $this
            ->params()
            ->definePageNav();

        $visitor = \XF::visitor();
        /** @var \XF\Repository\Conversation $conversionRepo */
        $conversionRepo = $this->repository('XF:Conversation');

        $finder = $conversionRepo->findConversationsStartedByUser($visitor);
        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $conversations = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'conversations' => $conversations,
            'conversations_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'conversations');

        return $this->api($data);
    }

    public function actionSingle($conversationId)
    {

    }
}