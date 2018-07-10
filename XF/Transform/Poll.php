<?php

namespace Xfrocks\Api\XF\Transform;

use XF\Mvc\Entity\ArrayCollection;
use Xfrocks\Api\Transform\AbstractHandler;

class Poll extends AbstractHandler
{
    const KEY_ID = 'poll_id';
    const KEY_QUESTION = 'poll_question';
    const KEY_VOTE_COUNT = 'poll_vote_count';
    const KEY_MAX_VOTES = 'poll_max_votes';

    const DYNAMIC_KEY_IS_OPEN = 'poll_is_open';
    const DYNAMIC_KEY_IS_VOTED = 'poll_is_voted';
    const DYNAMIC_KEY_RESPONSES = 'responses';

    public function getMappings()
    {
        return [
            'poll_id' => self::KEY_ID,
            'question' => self::KEY_QUESTION,
            'voter_count' => self::KEY_VOTE_COUNT,
            'max_votes' => self::KEY_MAX_VOTES,

            self::DYNAMIC_KEY_IS_OPEN,
            self::DYNAMIC_KEY_IS_VOTED,
            self::DYNAMIC_KEY_RESPONSES
        ];
    }

    public function calculateDynamicValue($key)
    {
        /** @var \XF\Entity\Poll $poll */
        $poll = $this->source;

        switch ($key) {
            case self::DYNAMIC_KEY_IS_OPEN:
                return !$poll->isClosed();
            case self::DYNAMIC_KEY_IS_VOTED:
                return $poll->hasVoted();
            case self::DYNAMIC_KEY_RESPONSES:
                /** @var \XF\Entity\PollResponse[]|ArrayCollection $responses */
                $responses = $poll->Responses;

                return $this->transformer->transformSubEntities($this, $key, $responses);
        }

        return null;
    }
}
