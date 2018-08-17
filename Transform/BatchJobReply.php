<?php

namespace Xfrocks\Api\Transform;

use XF\Mvc\Reply\AbstractReply;

class BatchJobReply extends AbstractHandler
{
    const DYNAMIC_KEY_ERROR = '_job_error';
    const DYNAMIC_KEY_MESSAGE = '_job_message';
    const DYNAMIC_KEY_RESPONSE_CODE = '_job_response_code';
    const DYNAMIC_KEY_RESULT = '_job_result';

    const RESULT_ERROR = 'error';
    const RESULT_MESSAGE = 'message';
    const RESULT_OK = 'ok';

    public function calculateDynamicValue($context, $key)
    {
        /** @var AbstractReply $reply */
        $reply = $context->data('reply');
        if (empty($reply)) {
            return null;
        }

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
                case self::DYNAMIC_KEY_RESULT:
                    return self::RESULT_OK;
            }
        } else {
            switch ($key) {
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

    public function getMappings($context)
    {
        return [
            self::DYNAMIC_KEY_ERROR,
            self::DYNAMIC_KEY_MESSAGE,
            self::DYNAMIC_KEY_RESULT,
        ];
    }

    public function onNewContext($context)
    {
        $data = parent::onNewContext($context);
        $data['reply'] = null;

        $contextSource = $context->getSource();
        if ($contextSource instanceof \XF\Mvc\Reply\Exception) {
            $data['reply'] = $contextSource->getReply();
        } elseif ($contextSource instanceof \XF\Mvc\Reply\AbstractReply) {
            $data['reply'] = $contextSource;
        }

        return $data;
    }

    public function onTransformed($context, array &$data)
    {
        $reply = $context->data('reply');
        if (is_object($reply) && $reply instanceof \Xfrocks\Api\Mvc\Reply\Api) {
            $data += $this->transformer->transformArray($context, '', $reply->getData());
        }

        parent::onTransformed($context, $data);
    }

    protected function prepareContextSelector($context)
    {
        // intentionally left blank
    }
}
