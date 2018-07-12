<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class PollResponse extends AbstractHandler
{
    const KEY_ID = 'response_id';
    const KEY_ANSWER = 'response_answer';

    const DYNAMIC_KEY_IS_VOTED = 'response_is_voted';

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XF\Entity\PollResponse $response */
        $response = $context->source;

        switch ($key) {
            case self::DYNAMIC_KEY_IS_VOTED:
                $parent = $context->parent;
                if (empty($parent)) {
                    return null;
                }

                /** @var \XF\Entity\Poll|null $poll */
                $poll = $parent->source;
                if (empty($poll)) {
                    return null;
                }

                return $poll->hasVoted($response->poll_response_id);
        }

        return null;
    }

    public function getMappings($context)
    {
        return [
            'poll_response_id' => self::KEY_ID,
            'response' => self::KEY_ANSWER,

            self::DYNAMIC_KEY_IS_VOTED
        ];
    }
}
