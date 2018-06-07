<?php

namespace Xfrocks\Api\Transform;

use XF\Mvc\Reply\AbstractReply;

class BatchJobReply extends AbstractHandler
{
    const DYNAMIC_KEY_ERROR = '_job_error';
    const DYNAMIC_KEY_MESSAGE = '_job_message';
    const DYNAMIC_KEY_RESPONSE = '_job_response';
    const DYNAMIC_KEY_RESPONSE_CODE = '_job_response_code';
    const DYNAMIC_KEY_RESULT = '_job_result';

    const RESULT_ERROR = 'error';
    const RESULT_MESSAGE = 'message';
    const RESULT_OK = 'ok';

    public function calculateDynamicValue($key)
    {
        /** @var AbstractReply $reply */
        $reply = $this->source;

        if ($reply instanceof \XF\Mvc\Reply\Error) {
            switch ($key) {
                case self::DYNAMIC_KEY_ERROR:
                    return implode(', ', $reply->getErrors());
                case self::DYNAMIC_KEY_RESULT:
                    return self::RESULT_ERROR;
            }
        } elseif ($reply instanceof \XF\Mvc\Reply\Message) {
            switch ($key) {
                case self::DYNAMIC_KEY_MESSAGE:
                    return $reply->getMessage();
                case self::DYNAMIC_KEY_RESULT:
                    return self::RESULT_MESSAGE;
            }
        } elseif ($reply instanceof \Xfrocks\Api\Mvc\Reply\Api) {
            switch ($key) {
                case self::DYNAMIC_KEY_RESPONSE:
                    return $reply->getData();
                case self::DYNAMIC_KEY_RESULT:
                    return self::RESULT_OK;
            }
        } else {
            switch ($key) {
                case self::DYNAMIC_KEY_RESPONSE:
                    // TODO
                    return null;
                case self::DYNAMIC_KEY_RESULT:
                    $responseCode = $reply->getResponseCode();
                    if ($responseCode >= 200 && $responseCode < 300) {
                        return self::RESULT_OK;
                    }

                    return self::RESULT_ERROR;
            }
        }

        switch ($key) {
            case self::DYNAMIC_KEY_RESPONSE_CODE:
                return $reply->getResponseCode();
        }

        return null;
    }

    public function getMappings()
    {
        return [
            self::DYNAMIC_KEY_ERROR,
            self::DYNAMIC_KEY_MESSAGE,
            self::DYNAMIC_KEY_RESPONSE,
            self::DYNAMIC_KEY_RESULT,
        ];
    }

    public function reset($source, $parent, $selector)
    {
        /** @var AbstractReply $reply */
        $reply = $source;
        if ($reply instanceof \XF\Mvc\Reply\Exception) {
            $this->reset($reply->getReply(), $parent, $selector);
            return;
        }

        parent::reset($source, $parent, $selector);
    }
}
