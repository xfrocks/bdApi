<?php

namespace Xfrocks\Api\XF\Transform;

use Xfrocks\Api\Transform\AbstractHandler;

class PollResponse extends AbstractHandler
{
    const KEY_ID = 'response_id';
    const KEY_ANSWER = 'response_answer';
    const KEY_VOTE_COUNT = 'response_vote_count';

    const DYNAMIC_KEY_IS_VOTED = 'response_is_voted';

    public function canView($context)
    {
        return true;
    }

    public function calculateDynamicValue($context, $key)
    {
        /** @var \XF\Entity\PollResponse $response */
        $response = $context->getSource();

        switch ($key) {
            case self::DYNAMIC_KEY_IS_VOTED:
                /** @var \XF\Entity\Poll|null $poll */
                $poll = \XF::em()->find('XF:Poll', $response->poll_id);
                if ($poll === null) {
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
            'response_vote_count' => self::KEY_VOTE_COUNT,

            self::DYNAMIC_KEY_IS_VOTED
        ];
    }
}
